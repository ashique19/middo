<?php

namespace App\Support;

use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Shared Middo float debit for partner withdrawals (cash till vs bank).
 */
class WithdrawalPayout
{
    /**
     * @return array{cash_entry_id: ?int, bank_entry_id: ?int, bank_account_id: ?int, attachment_path: ?string}
     */
    public static function debitMiddoFloat(
        string $channel,
        int $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        int $actorId,
        ?int $bankAccountId = null,
        ?UploadedFile $attachment = null,
        string $cashEntryType = 'withdrawal_paid',
    ): array {
        $attachmentPath = self::storeAttachment($attachment, $referenceId);

        if (! PayoutChannel::usesBankFloat($channel)) {
            $entry = MiddoCashLedger::debit(
                $amount,
                $cashEntryType,
                $referenceType,
                $referenceId,
                $description,
                $actorId
            );

            return [
                'cash_entry_id' => $entry->id,
                'bank_entry_id' => null,
                'bank_account_id' => null,
                'attachment_path' => $attachmentPath,
            ];
        }

        if (! $bankAccountId) {
            throw new \RuntimeException('Select a Middo bank account to pay this '.$channel.' withdrawal.');
        }

        $account = MiddoBankAccount::query()
            ->whereKey($bankAccountId)
            ->where('is_active', true)
            ->first();
        if (! $account) {
            throw new \RuntimeException('Bank account not found or inactive.');
        }

        $balance = MiddoBankLedger::balance((int) $account->id);
        if ($amount > $balance) {
            throw new \RuntimeException(
                'Bank account balance ৳'.number_format($balance).' is lower than payout ৳'.number_format($amount).'.'
            );
        }

        $entry = MiddoBankLedger::debit(
            (int) $account->id,
            $amount,
            MiddoBankLedgerEntry::TYPE_WITHDRAWAL_PAID,
            $referenceType,
            $referenceId,
            $description.' · '.PayoutChannel::label($channel),
            $actorId
        );

        return [
            'cash_entry_id' => null,
            'bank_entry_id' => $entry->id,
            'bank_account_id' => (int) $account->id,
            'attachment_path' => $attachmentPath,
        ];
    }

    public static function storeAttachment(?UploadedFile $file, int $requestId): ?string
    {
        if (! $file) {
            return null;
        }

        $relativePath = 'img/withdrawal-attachments';
        $directory = public_path($relativePath);
        File::ensureDirectoryExists($directory);

        $extension = strtolower($file->extension() ?: 'jpg');
        $filename = 'withdrawal-'.$requestId.'-'.now()->format('YmdHis').'.'.$extension;
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;

        $sourcePath = $file->getRealPath();
        if (! $sourcePath || ! is_readable($sourcePath)) {
            throw new \RuntimeException('Uploaded attachment is no longer available. Please try again.');
        }

        if (! File::copy($sourcePath, $destination)) {
            throw new \RuntimeException('Could not save the uploaded attachment.');
        }

        return $relativePath.'/'.$filename;
    }
}

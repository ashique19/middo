<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print {{ $box->qr_code_id }} | Middo Box</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/settings/favicon.ico') }}">
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f2e9;
            font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
            color: #1A1C19;
        }

        .label {
            width: 320px;
            padding: 28px 24px;
            background: #fff;
            border: 2px solid #e5e7eb;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .logo {
            height: 28px;
            margin-bottom: 18px;
        }

        .qr-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 256px;
            margin-bottom: 16px;
        }

        .qr-wrap canvas,
        .qr-wrap img {
            display: block;
        }

        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #ab3f00;
            margin-bottom: 6px;
        }

        .meta {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        button {
            border: 0;
            border-radius: 12px;
            padding: 10px 18px;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
        }

        .print-btn {
            background: #ab3f00;
            color: #fff;
        }

        .close-btn {
            background: #f3f4f6;
            color: #374151;
        }

        @media print {
            body {
                background: #fff;
            }

            .label {
                border: 1px solid #d1d5db;
                box-shadow: none;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="label">
        <img src="{{ asset('img/settings/logo.png') }}" alt="Middo" class="logo" onerror="this.style.display='none'">

        <div id="qrcode" class="qr-wrap" data-code="{{ $box->qr_code_id }}"></div>

        <div class="code">{{ $box->qr_code_id }}</div>
        <div class="meta">{{ str($box->box_model_type)->headline() }} · Box #{{ $box->id }}</div>

        <div class="actions">
            <button type="button" class="print-btn" onclick="window.print()">Print</button>
            <button type="button" class="close-btn" onclick="window.close()">Close</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const container = document.getElementById('qrcode');
        const code = container.dataset.code;

        new QRCode(container, {
            text: code,
            width: 240,
            height: 240,
            colorDark: '#1A1C19',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H,
        });

        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 400);
        });
    </script>
</body>
</html>

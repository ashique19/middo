<?php

namespace Database\Seeders;

use App\Models\SitePage;
use Illuminate\Database\Seeder;

class SitePageSeeder extends Seeder
{
    public function run(): void
    {
        SitePage::query()->updateOrCreate(
            ['slug' => 'privacy'],
            [
                'title' => 'Privacy Policy',
                'is_published' => true,
                'body' => <<<'HTML'
<p>Last updated: 17 July 2026</p>

<p>Middo (“we”, “us”) provides corporate meal ordering and delivery services in Bangladesh. This Privacy Policy explains how we collect, use, and protect information when you use the Middo website, corporate portal, and mobile apps.</p>

<h2>1. Information we collect</h2>
<ul>
    <li><strong>Account details</strong> — buyer name, company name, mobile number, email, delivery address, city, and area.</li>
    <li><strong>Order activity</strong> — menus selected, quantities, delivery dates/times, payment and Middo Balance activity, and support messages.</li>
    <li><strong>Device data</strong> — push notification tokens and basic device identifiers when you use the corporate mobile app.</li>
    <li><strong>Operational logs</strong> — order status history and Middo Box custody events needed to fulfill deliveries.</li>
</ul>

<h2>2. How we use information</h2>
<ul>
    <li>Authenticate your account and communicate about orders, OTP verification, and support.</li>
    <li>Schedule, prepare, deliver, and track meals and Middo Boxes.</li>
    <li>Process Middo Balance top-ups and order prepayments through our payment partners or temporary checkout.</li>
    <li>Improve service reliability, prevent fraud, and meet legal obligations.</li>
</ul>

<h2>3. Sharing</h2>
<p>We share only what is required with kitchens, riders, and operations staff fulfilling your orders, and with payment or SMS providers acting on our instructions. We do not sell personal data.</p>

<h2>4. Retention &amp; security</h2>
<p>We retain account and order records for as long as needed to provide the service and meet accounting or legal requirements. Access is limited to authorized Middo roles and protected with industry-standard safeguards.</p>

<h2>5. Your choices</h2>
<p>You may update profile details from the corporate portal or app. For account closure, balance questions, or privacy requests, contact Middo support via the Contact page or in-app support on an order.</p>

<h2>6. Contact</h2>
<p>Questions about this policy: use the Middo Contact form or email the address published on our Contact page.</p>
HTML,
            ]
        );

        SitePage::query()->updateOrCreate(
            ['slug' => 'terms'],
            [
                'title' => 'Terms & Conditions',
                'is_published' => true,
                'body' => <<<'HTML'
<p>Last updated: 17 July 2026</p>

<p>These Terms &amp; Conditions govern use of Middo’s corporate meal platform (website, portal, and mobile apps). By creating an account or placing an order, you agree to these terms.</p>

<h2>1. Who may use Middo</h2>
<p>Corporate accounts are for office buyers ordering meals for workplace delivery. Kitchen and delivery accounts are issued separately by Middo operations. You must provide accurate buyer and delivery details and keep your login credentials secure.</p>

<h2>2. Orders &amp; scheduling</h2>
<ul>
    <li>Orders may be placed for eligible menu items and delivery windows shown in the product.</li>
    <li>While an order remains <strong>pending</strong> (before kitchen dispatch), you may edit quantities or cancel it from Scheduled / Active Orders.</li>
    <li>After kitchen acceptance or dispatch, changes may no longer be available; contact support for help.</li>
</ul>

<h2>3. Pricing, Middo Balance &amp; payments</h2>
<ul>
    <li>Menu prices are shown in Bangladeshi Taka (৳).</li>
    <li>Middo Balance can be topped up through the payment checkout presented in the product (including our temporary pseudo gateway during development).</li>
    <li>Some orders require prepayment (for example when the receiver differs from the account holder, or when active-order limits apply). Unpaid required prepayment blocks scheduling.</li>
    <li>Unused Middo Balance remains on the account for future orders unless Middo support agrees otherwise.</li>
</ul>

<h2>4. Delivery &amp; Middo Boxes</h2>
<p>Meals are delivered in Middo Boxes. Empty boxes remaining at your office stay in your custody until a Middo rider collects them on a subsequent delivery or pickup run. Please keep boxes accessible and undamaged.</p>

<h2>5. Acceptable use</h2>
<p>Do not misuse the platform, attempt unauthorized access, or submit abusive content in support threads. Middo may suspend accounts that violate these terms or applicable law.</p>

<h2>6. Limitation of liability</h2>
<p>Middo works with partner kitchens and riders to fulfill orders. To the extent permitted by law, Middo is not liable for indirect or consequential losses. Nothing in these terms limits rights you cannot waive under Bangladesh law.</p>

<h2>7. Changes</h2>
<p>We may update these terms and will revise the “Last updated” date above. Continued use after changes means you accept the updated terms.</p>

<h2>8. Contact</h2>
<p>For questions about these terms, use the Middo Contact page.</p>
HTML,
            ]
        );
    }
}

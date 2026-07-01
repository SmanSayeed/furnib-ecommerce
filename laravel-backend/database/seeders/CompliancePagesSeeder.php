<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Seeds the four legal / about CMS pages the payment gateway requires
 * (compliance #1, #2, #3). Idempotent: an existing page keeps its current
 * title, body and publish state — only missing pages are created, so the
 * owner's edited content is never overwritten.
 *
 * Run standalone: php artisan db:seed --class=CompliancePagesSeeder
 */
class CompliancePagesSeeder extends Seeder
{
    public function run(): void
    {
        $edit = '<!-- EDIT: owner to replace with real content -->';

        $pages = [
            [
                'slug' => 'terms-and-conditions',
                'title' => 'Terms & Conditions',
                'position' => 90,
                'is_system' => true,
                'body_html' => <<<HTML
                    {$edit}
                    <h2>Terms &amp; Conditions</h2>
                    <p>By placing an order on this website you agree to the terms below.</p>
                    <h3>Orders &amp; Pricing</h3>
                    <p>All prices are shown in Bangladeshi Taka (BDT) and include applicable charges unless stated otherwise.</p>
                    <h3>Use of the Site</h3>
                    <p>You agree to provide accurate information and to use the site lawfully.</p>
                    <h3>Contact</h3>
                    <p>For any questions about these terms, please contact us using the details in the footer.</p>
                    HTML,
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'position' => 91,
                'is_system' => true,
                'body_html' => <<<HTML
                    {$edit}
                    <h2>Privacy Policy</h2>
                    <p>This policy explains what information we collect and how we use it.</p>
                    <h3>Information We Collect</h3>
                    <p>We collect the name, mobile number, email and delivery address you provide at checkout.</p>
                    <h3>How We Use It</h3>
                    <p>We use your information only to process and deliver your orders and to contact you about them.</p>
                    <h3>Data Sharing</h3>
                    <p>We share information with delivery and payment partners only as needed to complete your order.</p>
                    HTML,
            ],
            [
                'slug' => 'return-refund-policy',
                'title' => 'Return & Refund Policy',
                'position' => 92,
                'is_system' => true,
                'body_html' => <<<HTML
                    {$edit}
                    <h2>Return &amp; Refund Policy</h2>
                    <p>We want you to be satisfied with your purchase.</p>
                    <h3>Returns</h3>
                    <p>Eligible items may be returned in their original condition within the stated window.</p>
                    <h3>Refunds</h3>
                    <p>Refunds are processed within 7 to 10 working days.</p>
                    <p>Refunds are issued to the original payment method after the returned item is received and checked.</p>
                    HTML,
            ],
            [
                'slug' => 'about-us',
                'title' => 'About Us',
                'position' => 93,
                'body_html' => <<<HTML
                    {$edit}
                    <h2>About Us</h2>
                    <h3>Company details</h3>
                    <p>Owner to add the company name, a short description and the year established.</p>
                    <h3>Management details</h3>
                    <p>Owner to add the names and roles of the key management / proprietors.</p>
                    <h3>Legal</h3>
                    <p>Trade License No.: (owner to add)</p>
                    <p>Registered Address: (owner to add)</p>
                    HTML,
            ],
        ];

        foreach ($pages as $page) {
            $isSystem = $page['is_system'] ?? false;

            $model = Page::query()->firstOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'body_html' => $page['body_html'],
                    'is_published' => true,
                    'is_system' => $isSystem,
                    'position' => $page['position'],
                ],
            );

            // For legal pages that already existed, ensure they are flagged as
            // system + published without touching the owner's edited title/body.
            if ($isSystem && (! $model->is_system || ! $model->is_published)) {
                $model->update(['is_system' => true, 'is_published' => true]);
            }
        }
    }
}

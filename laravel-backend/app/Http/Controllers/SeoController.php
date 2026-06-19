<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\Response;

/**
 * Public SEO endpoints: XML sitemap and robots.txt. Only published catalog is
 * exposed (enforced by the sitemap builder).
 */
class SeoController extends Controller
{
    public function sitemap(SitemapBuilder $builder): Response
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($builder->entries() as $entry) {
            $xml .= '  <url><loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>';

            if ($entry['lastmod'] !== null) {
                $xml .= '<lastmod>'.$entry['lastmod'].'</lastmod>';
            }

            $xml .= "</url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /settings',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}

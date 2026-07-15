<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Str;

/**
 * Access control + addressing for the Meta/Google product feed.
 *
 * The feed exposes the full catalogue with prices and stock, so it must not be
 * world-readable. It lives behind THREE gates: an on/off switch, an unguessable
 * path segment (`feed_slug`), and HTTP Basic auth (`feed_username` /
 * `feed_password`, the password stored encrypted). Meta's scheduled-feed fetcher
 * supports Basic auth on the feed URL, which is exactly how the owner wires it up.
 */
final class FeedAccess
{
    public const GROUP = 'facebook_commerce';

    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::GROUP, 'feed_enabled', false);
    }

    public function slug(): ?string
    {
        $slug = $this->settings->get(self::GROUP, 'feed_slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function username(): ?string
    {
        $user = $this->settings->get(self::GROUP, 'feed_username');

        return is_string($user) && $user !== '' ? $user : null;
    }

    public function password(): ?string
    {
        $pass = $this->settings->get(self::GROUP, 'feed_password');

        return is_string($pass) && $pass !== '' ? $pass : null;
    }

    /**
     * The absolute feed URL the owner pastes into Commerce Manager. Built from the
     * backend origin (the feed is served by Laravel, not the Next storefront).
     */
    public function url(): ?string
    {
        $slug = $this->slug();

        if ($slug === null) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/feed/'.$slug.'/products.csv';
    }

    /**
     * Whether an incoming request's path token + Basic credentials match. Uses
     * constant-time comparisons so the token/password can't be timing-probed.
     */
    public function verify(string $token, ?string $user, ?string $pass): bool
    {
        $slug = $this->slug();
        $storedUser = $this->username();
        $storedPass = $this->password();

        if ($slug === null || $storedUser === null || $storedPass === null) {
            return false;
        }

        return hash_equals($slug, $token)
            && hash_equals($storedUser, (string) $user)
            && hash_equals($storedPass, (string) $pass);
    }

    /**
     * Create the slug + username + password if any are missing (called when the
     * feed is first switched on). Returns the plaintext password only when it is
     * freshly generated, so the admin can note it once — it is write-only after.
     */
    public function ensureCredentials(): ?string
    {
        $freshPassword = null;

        if ($this->slug() === null) {
            $this->settings->set(self::GROUP, 'feed_slug', Str::lower(Str::random(24)));
        }

        if ($this->username() === null) {
            $this->settings->set(self::GROUP, 'feed_username', 'furnib-feed');
        }

        if ($this->password() === null) {
            $freshPassword = Str::random(32);
            $this->settings->set(self::GROUP, 'feed_password', $freshPassword, isSecret: true);
        }

        return $freshPassword;
    }

    /**
     * Rotate the slug AND password, invalidating any previously shared feed URL.
     * Returns the new plaintext password (shown once).
     */
    public function regenerate(): string
    {
        $password = Str::random(32);

        $this->settings->set(self::GROUP, 'feed_slug', Str::lower(Str::random(24)));
        $this->settings->set(self::GROUP, 'feed_password', $password, isSecret: true);

        return $password;
    }
}

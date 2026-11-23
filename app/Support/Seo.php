<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SocialLink;

/**
 * Search-engine and tracking helpers, all set on the "SEO and tracking" screen (super user only):
 * the title pattern, a default description and share picture, hiding the whole site from search engines, verification codes,
 * structured data (JSON-LD), robots.txt, Google Tag Manager / Google Analytics, and custom code for the head and the body.
 */
class Seo
{
    public const DEFAULT_PATTERN = ':page | :site';

    private static function site(): string
    {
        return (string) Setting::get('site_name', config('app.name'));
    }

    /** The page title. Pages give "Name | Site"; the name is taken out and put in the pattern. A title typed in full by an editor is kept as it is. */
    public static function title(string $raw): string
    {
        $site = static::site();
        $raw = trim($raw);
        $suffix = ' | '.$site;
        if ($raw === '' || $raw === $site) {
            return trim((string) Setting::get('seo_home_title')) ?: $site;
        }
        if (! str_ends_with($raw, $suffix)) {
            return $raw;
        }
        $page = substr($raw, 0, -strlen($suffix));
        $pattern = trim((string) Setting::get('seo_title_pattern')) ?: static::DEFAULT_PATTERN;

        return trim(str_replace([':page', ':site'], [$page, $site], $pattern));
    }

    public static function description(string $page): string
    {
        $page = trim($page);
        if ($page !== '') {
            return $page;
        }

        return trim((string) Setting::get('seo_default_description')) ?: trim((string) Setting::get('tagline', __('site.tagline')));
    }

    public static function ogImage(string $page): string
    {
        if (trim($page) !== '') {
            return trim($page);
        }
        $default = (string) Setting::get('seo_og_image');

        return $default !== '' ? asset('storage/'.$default) : '';
    }

    public static function siteHidden(): bool
    {
        return (bool) filter_var(Setting::get('seo_noindex_site', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** A code from Google, Bing or Facebook: letters, digits, - and _ only, so nothing else can slip into the page. */
    private static function code(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) Setting::get($key));
    }

    /** The meta tags that prove to Google, Bing and Facebook that the site is yours. */
    public static function verificationTags(): string
    {
        $out = '';
        foreach (['seo_google_verification' => 'google-site-verification', 'seo_bing_verification' => 'msvalidate.01', 'seo_facebook_verification' => 'facebook-domain-verification'] as $key => $name) {
            if (($c = static::code($key)) !== '') {
                $out .= '<meta name="'.$name.'" content="'.e($c)."\">\n    ";
            }
        }

        return $out;
    }

    public static function gtmId(): string
    {
        $id = strtoupper(trim((string) Setting::get('seo_gtm_id')));

        return preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) ? $id : '';
    }

    public static function ga4Id(): string
    {
        $id = strtoupper(trim((string) Setting::get('seo_ga4_id')));

        return preg_match('/^G-[A-Z0-9]{4,14}$/', $id) ? $id : '';
    }

    /** Tracking runs only on the real site, never on a computer's own address, and (unless switched on) never for the team. */
    public static function trackingAllowed(): bool
    {
        $host = strtolower(request()->getHost());
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || str_ends_with($host, '.localhost') || str_ends_with($host, '.test')) {
            return false;
        }
        if (auth()->check() && auth()->user()->isStaff() && ! filter_var(Setting::get('seo_track_staff', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return true;
    }

    /** Is the cookie notice on? Then counting waits for the visitor's OK. (On unless the super user switches it off.) */
    public static function consentOn(): bool
    {
        $v = Setting::get('consent_on');

        return $v === null || $v === '' ? true : (bool) filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /** One piece of counting code: with the cookie notice on it is held back (type text/plain) until the visitor says OK. */
    private static function piece(string $code, ?string $src = null): string
    {
        if (! static::consentOn()) {
            return $src ? "<script async src=\"{$src}\"></script>\n    " : "<script>{$code}</script>\n    ";
        }

        return '<script type="text/plain" data-consent="analytics"'.($src ? " data-src=\"{$src}\"" : '').'>'.($src ? '' : $code)."</script>\n    ";
    }

    /** Google Tag Manager (or, if only that is set, Google Analytics) for the head. */
    public static function trackingHead(): string
    {
        if (! static::trackingAllowed()) {
            return '';
        }
        if ($gtm = static::gtmId()) {
            return static::piece("(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm}');");
        }
        if ($ga = static::ga4Id()) {
            return static::piece('', "https://www.googletagmanager.com/gtag/js?id={$ga}")
                .static::piece("window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$ga}');");
        }

        return '';
    }

    /** The part of Google Tag Manager that goes straight after the opening body tag. (Left out when the cookie notice is on: it cannot ask for an OK without scripts.) */
    public static function trackingBody(): string
    {
        if (! static::trackingAllowed() || ! ($gtm = static::gtmId()) || static::consentOn()) {
            return '';
        }

        return "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$gtm}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }

    /** Code the super user typed for the head, the start of the body, or the end of the body. Printed as it is. */
    public static function custom(string $where): string
    {
        $key = ['head' => 'seo_code_head', 'body_start' => 'seo_code_body_start', 'body_end' => 'seo_code_body_end'][$where] ?? null;
        $code = $key ? (string) Setting::get($key) : '';

        return $code !== '' ? $code."\n" : '';
    }

    private static function script(array $data): string
    {
        return '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)."</script>\n";
    }

    private static function organization(): array
    {
        $org = ['@type' => 'Organization', 'name' => static::site(), 'url' => url('/')];
        if ($logo = Setting::get('logo')) {
            $org['logo'] = asset('storage/'.$logo);
        }
        $same = SocialLink::query()->where('is_active', true)->pluck('url')->filter(fn ($u) => preg_match('~^https?://~i', (string) $u))->values()->all();
        if ($same) {
            $org['sameAs'] = $same;
        }

        return $org;
    }

    /** For the home page: the organisation and the site with its search box. */
    public static function homeJsonLd(): string
    {
        return static::script([
            '@context' => 'https://schema.org',
            '@graph' => [
                static::organization() + ['@id' => url('/').'#organization'],
                ['@type' => 'WebSite', 'name' => static::site(), 'url' => url('/'), 'description' => static::description(''), 'publisher' => ['@id' => url('/').'#organization'],
                    'potentialAction' => ['@type' => 'SearchAction', 'target' => url('/search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
            ],
        ]);
    }

    /** For one article or story. $model needs title, publish_date, updated_at, main_image and (optionally) contributor. */
    public static function articleJsonLd($model, string $url, string $description): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => mb_substr((string) $model->mainTitle(), 0, 110),
            'description' => $description,
            'mainEntityOfPage' => $url,
            'inLanguage' => app()->getLocale(),
            'datePublished' => ($model->publish_date ?: $model->created_at)?->toIso8601String(),
            'dateModified' => ($model->updated_at ?: $model->created_at)?->toIso8601String(),
            'author' => ($model->relationLoaded('contributor') && $model->contributor) ? ['@type' => 'Person', 'name' => $model->contributor->name] : ['@type' => 'Organization', 'name' => static::site()],
            'publisher' => static::organization(),
        ];
        if ($model->main_image) {
            $data['image'] = asset('storage/'.$model->main_image);
        }

        return static::script($data);
    }

    /** For the FAQ page: Google can show the questions and answers in the results. @param \Illuminate\Support\Collection $items rows with question and answer (html) */
    public static function faqJsonLd($items): string
    {
        $entities = $items->map(fn ($i) => [
            '@type' => 'Question',
            'name' => $i['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $i['answer'])))],
        ])->values()->all();

        return $entities === [] ? '' : static::script(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities]);
    }

    /** The robots.txt text: private parts closed, the sitemap named, and the lines the super user added. */
    public static function robotsTxt(): string
    {
        if (static::siteHidden()) {
            // Every crawler is kept out, except the link checkers of Facebook and Meta: they must read the privacy and terms pages
            // for the "Continue with Facebook" app. Search engines stay out, and every page still says noindex.
            return "User-agent: facebookexternalhit\nUser-agent: Facebot\nUser-agent: meta-externalagent\nDisallow: /admin\nDisallow: /account\nDisallow: /login\nDisallow: /register\n\nUser-agent: *\nDisallow: /\n";
        }
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /account', 'Disallow: /login', 'Disallow: /register', 'Disallow: /forgot-password', 'Disallow: /reset-password', 'Disallow: /email/', 'Disallow: /search', 'Disallow: /language/', 'Disallow: /quiz/'];
        if ($extra = trim((string) Setting::get('seo_robots_extra'))) {
            $lines[] = $extra;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');

        return implode("\n", $lines)."\n";
    }
}

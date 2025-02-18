<?php
/*
 * This file is part of the pushull-translation-provider package.
 *
 * (c) 2022 m2m server software gmbh <tech@m2m.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pushull\PushullTranslationProvider\Api;

use Psr\Log\LoggerInterface;
use Pushull\PushullTranslationProvider\Api\DTO\Translation;
use Pushull\PushullTranslationProvider\Api\DTO\Unit;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UnitApi
{
    /** @var array<string,array<string,Unit>> */
    private static $units = [];

    /** @var HttpClientInterface */
    private static $client;

    /** @var LoggerInterface */
    private static $logger;

    public static function setup(
        HttpClientInterface $client,
        LoggerInterface $logger
    ): void {
        self::$client = $client;
        self::$logger = $logger;

        self::$units = [];
    }

    /**
     * @return array<string,Unit>
     *
     * @throws ExceptionInterface
     */
    public static function getUnits(Translation $translation, bool $reload = false): array
    {
        if ($reload) {
            unset(self::$units[$translation->filename]);
        }

        if (isset(self::$units[$translation->filename])) {
            return self::$units[$translation->filename];
        }

        /**
         * GET /api/translations/(string: project)/(string: component)/(string: language)/units/.
         *
         * @see GET /api/translations/(string: project)/(string: component)/(string: language)/units/
         */
        $page = 1;
        do {
            $url = $translation->units_list_url.
                (null === parse_url($translation->units_list_url, PHP_URL_QUERY) ? '?' : '&').http_build_query(['page' => $page]);
            $response = self::$client->request('GET', $url);

            if (200 !== $response->getStatusCode()) {
                self::$logger->debug($response->getStatusCode().': '.$response->getContent(false));
                throw new ProviderException('Unable to get pushull units for '.$translation->filename.'.', $response);
            }

            $results = $response->toArray();
            foreach ($results['results'] ?? [] as $result) {
                $unit = new Unit($result);
                self::$units[$translation->filename][$unit->context] = $unit;
                self::$logger->debug('Loaded unit '.$translation->filename.' '.$unit->context);
            }

            ++$page;
            $nextUrl = $results['next'] ?? null;
        } while (null !== $nextUrl);

        return self::$units[$translation->filename] ?? [];
    }

    /**
     * @throws ExceptionInterface
     */
    public static function hasUnit(Translation $translation, string $key): bool
    {
        if (isset(self::$units[$translation->filename][$key])) {
            return true;
        }

        if (isset(self::$units[$translation->filename])) {
            // already tried to load units from server before

            return false;
        }

        self::getUnits($translation);

        if (isset(self::$units[$translation->filename][$key])) {
            return true;
        }

        return false;
    }

    /**
     * @throws ExceptionInterface
     */
    public static function getUnit(Translation $translation, string $key): ?Unit
    {
        if (self::hasUnit($translation, $key)) {
            return self::$units[$translation->filename][$key];
        }

        return null;
    }

    /**
     * @throws ExceptionInterface
     */
    public static function addUnit(Translation $translation, string $key, string $value): void
    {
        /**
         * POST /api/translations/(string: project)/(string: component)/(string: language)/units/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#post--api-translations-(string-project)-(string-component)-(string-language)-units-
         */
        $response = self::$client->request('POST', $translation->units_list_url, [
            'body' => ['key' => $key, 'value' => $value],
        ]);

        if (200 !== $response->getStatusCode()) {
            self::$logger->debug($response->getStatusCode().': '.$response->getContent(false));
            throw new ProviderException('Unable to add pushull unit for '.$translation->filename.' '.$key.'.', $response);
        }

        self::$logger->debug('Added unit '.$translation->filename.' '.$key);
    }

    /**
     * @throws ExceptionInterface
     */
    public static function updateUnit(Unit $unit, string $value): void
    {
        /**
         * PATCH /api/units/(int: id)/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#patch--api-units-(int-id)-
         */
        $response = self::$client->request('PATCH', $unit->url, [
            'body' => ['target' => $value, 'state' => $value ? 20 : 0],
        ]);

        if (200 !== $response->getStatusCode()) {
            self::$logger->debug($response->getStatusCode().': '.$response->getContent(false));
            throw new ProviderException('Unable to update pushull unit for '.$unit->context.' '.$value.'.', $response);
        }

        self::$logger->debug('Updated unit '.$unit->context.' '.$value);
    }

    /**
     * @throws ExceptionInterface
     */
    public static function deleteUnit(Unit $unit): void
    {
        /**
         * DELETE /api/units/(int: id)/.
         *
         * @see https://docs.weblate.org/en/latest/api.html#delete--api-units-(int-id)-
         */
        $response = self::$client->request('DELETE', $unit->url);

        if (204 !== $response->getStatusCode()) {
            self::$logger->debug($response->getStatusCode().': '.$response->getContent(false));
            throw new ProviderException('Unable to delete pushull unit for '.$unit->context.'.', $response);
        }

        self::$logger->debug('Deleted unit '.$unit->context);
    }
}

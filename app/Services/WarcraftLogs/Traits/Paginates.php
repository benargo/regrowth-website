<?php

namespace App\Services\WarcraftLogs\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

trait Paginates
{
    /**
     * Fetch all pages and collect items, deduplicating by code.
     *
     * @param  callable(int $page): array{items: array, hasMorePages: bool}  $pageFetcher
     */
    protected function paginateAll(callable $pageFetcher): Collection
    {
        $results = collect();
        $page = 1;

        do {
            $pageResult = $pageFetcher($page);

            foreach ($pageResult['items'] as $item) {
                $results[$item->code] = $item;
            }

            $hasMorePages = $pageResult['hasMorePages'];
            $page++;
        } while ($hasMorePages);

        return $results;
    }

    /**
     * Fetch all pages across multiple tag IDs, deduplicating items by code.
     *
     * @param  array<int>  $tagIDs
     * @param  callable(int $tagID): callable(int $page): array{items: array, hasMorePages: bool}  $pageFetcherFactory
     */
    protected function paginateAllAcrossTags(array $tagIDs, callable $pageFetcherFactory): Collection
    {
        $allItems = collect();

        foreach ($tagIDs as $tagID) {
            $tagItems = $this->paginateAll($pageFetcherFactory($tagID));

            foreach ($tagItems as $item) {
                $allItems[$item->code] = $item;
            }
        }

        return $allItems;
    }

    /**
     * Lazily iterate all pages, yielding items one by one.
     *
     * @param  callable(int $page): array{items: array, hasMorePages: bool}  $pageFetcher
     */
    protected function paginateLazy(callable $pageFetcher): LazyCollection
    {
        return LazyCollection::make(function () use ($pageFetcher) {
            $page = 1;

            do {
                $pageResult = $pageFetcher($page);

                foreach ($pageResult['items'] as $item) {
                    yield $item;
                }

                $hasMorePages = $pageResult['hasMorePages'];
                $page++;
            } while ($hasMorePages);
        });
    }

    /**
     * Lazily iterate across multiple tag IDs, deduplicating items by code.
     *
     * @param  array<int>  $tagIDs
     * @param  callable(int $tagID): LazyCollection  $lazyFactory
     */
    protected function paginateLazyAcrossTags(array $tagIDs, callable $lazyFactory): LazyCollection
    {
        return LazyCollection::make(function () use ($tagIDs, $lazyFactory) {
            $seenCodes = [];

            foreach ($tagIDs as $tagID) {
                foreach ($lazyFactory($tagID) as $item) {
                    if (isset($seenCodes[$item->code])) {
                        continue;
                    }
                    $seenCodes[$item->code] = true;
                    yield $item;
                }
            }
        });
    }
}

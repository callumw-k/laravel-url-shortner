<?php

namespace App\Jobs;

use App\Events\LinkImageUpdated;
use App\Models\Link;
use App\Observers\BookmarkUrlCrawler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Log;
use Spatie\Crawler\Crawler;

class CrawlUrlJob implements ShouldQueue
{
    use Queueable;

    public Link $link;

    /**
     * Create a new job instance.
     */
    public function __construct(Link $link)
    {
        $this->link = $link;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->crawlWithPlaywright();

        $this->crawlWithBrowsershot();

    }

    public function crawlWithPlaywright(): void
    {

        Log::info("Running crawl with playwright");

        $response = Http::post('playwright:3000', [
            'url' => $this->link->original_url,
        ]);

        if ($response->failed()) {
            return;
        }

        $json = $response->json();

        $success = $this->link->update(['title' => $json['title'], 'image' => $json['imageUrl']]);

        if (!$success) {
            Log::error("Failed to update link");
            return;
        }

        Log::info("Attempting to dispatch event", ['link', $this->link]);

        event(new LinkImageUpdated($this->link));
    }

    public function crawlWithBrowsershot(): void
    {
        Crawler::create()->setCrawlObserver(new BookmarkUrlCrawler($this->link))
            ->setMaximumDepth(0)
            ->setUserAgent('FacebookExternalHit/1.1')
            ->setTotalCrawlLimit(1)
            ->startCrawling($this->link->original_url);
    }

}

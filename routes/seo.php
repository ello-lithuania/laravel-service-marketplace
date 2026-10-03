<?php

// SEO: sitemap.xml ir robots.txt (Etapas 8)

use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Seo\SitemapController;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Support\Facades\Route;

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('seo.sitemap');
Route::get('sitemaps/{section}/{page}.xml', [SitemapController::class, 'show'])
    ->whereIn('section', SitemapBuilder::SECTIONS)
    ->whereNumber('page')
    ->name('seo.sitemap.section');

// public/robots.txt ištrintas: statinį failą web serveris atiduotų pirma, ir šis maršrutas niekada nesuveiktų
Route::get('robots.txt', RobotsController::class)->name('seo.robots');

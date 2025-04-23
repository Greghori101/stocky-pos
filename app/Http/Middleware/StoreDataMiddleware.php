<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;
use App\Models\Category;
use App\Models\StoreContact;
use App\Models\SocialMediaUrl;
use App\Models\Banner;
use App\Models\StoreSetting;
use App\utils\helpers;

class StoreDataMiddleware
{
    public function handle($request, Closure $next)
    {
        if ($request->is('store') || $request->is('store/*')) {
            $categories = Category::whereNull('deleted_at')->take(10)->get();

            $StoreContact = StoreContact::whereNull('deleted_at')->first();
            $footer = $StoreContact ? [
                'company_name' => $StoreContact->company_name,
                'company_phone' => $StoreContact->company_phone,
                'company_email' => $StoreContact->company_email,
                'company_adress' => $StoreContact->company_adress,
                'company_description' => $StoreContact->company_description,
            ] : [
                'company_name' => '',
                'company_phone' => '',
                'company_email' => '',
                'company_adress' => '',
                'company_description' => '',
            ];

            $socialurls = SocialMediaUrl::whereNull('deleted_at')->first();
            $footer = array_merge($footer, $socialurls ? [
                'facebook_url' => $socialurls->facebook_url,
                'twitter_url' => $socialurls->twitter_url,
                'instagram_url' => $socialurls->instagram_url,
                'google_url' => $socialurls->google_url,
                'youtube_url' => $socialurls->youtube_url,
            ] : [
                'facebook_url' => '',
                'twitter_url' => '',
                'instagram_url' => '',
                'google_url' => '',
                'youtube_url' => '',
            ]);

            $banners = Banner::whereNull('deleted_at')->first();
            $banner = $banners ? [
                'banner1_path' => $banners->banner1_path,
                'banner2_path' => $banners->banner2_path,
            ] : [
                'banner1_path' => '',
                'banner2_path' => '',
            ];

            $helpers = new Helpers();
            $symbol_currency = $helpers->Get_Currency();

            $StoreSetting = StoreSetting::whereNull('deleted_at')->first();
            $store_logo = $StoreSetting->logo;

            View::share('categories', $categories);
            View::share('footer', $footer);
            View::share('banner', $banner);
            View::share('store_logo', $store_logo);
            View::share('symbol_currency', $symbol_currency);
        }

        return $next($request);
    }
}

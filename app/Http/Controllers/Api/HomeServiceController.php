<?php

namespace App\Http\Controllers\Api;

use App\Models\HomeService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\HomeServiceApiResource;

class HomeServiceController extends Controller
{
    public function index(Request $request) {
        //with untuk ambil relasi langsung dari model (eager loading)
        $homeServices = HomeService::with(['category']);

        if($request->has('category_id')) {
            $homeServices->where('category_id', $request->input('category_id'));
        }

        if($request->has('is_popular')) {
            $homeServices->where('is_popular', $request->input('is_popular'));
        }

        if($request->has('limit')) {
            $homeServices->limit($request->input('limit'));
        }

        //mengembalikan nilai berupa collection
        return HomeServiceApiResource::collection($homeServices->get());
    }

    public function show(HomeService $homeService) {
        //load untuk model binding (eager loading)
        $homeService->load(['category', 'serviceBenefits', 'serviceTestimonials']);

        return new HomeServiceApiResource($homeService);
    }


}

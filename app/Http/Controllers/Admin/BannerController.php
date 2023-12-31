<?php

namespace App\Http\Controllers\Admin;

use App\Models\Banner;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Translation;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    function index()
    {
        $banners = Banner::with('module')->where('module_id', Config::get('module.current_module_id'))->latest()->paginate(config('default_pagination'));
        return view('admin-views.banner.index', compact('banners'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:191',
            'image' => 'required',
            'banner_type' => 'required',
            'zone_id' => 'required',
            'store_id' => 'required_if:banner_type,store_wise',
            'item_id' => 'required_if:banner_type,item_wise',
            'title.0' => 'required',
        ], [
            'zone_id.required' => translate('messages.select_a_zone'),
            'store_id.required_if'=> translate('messages.store is required when banner type is store wise'),
            'item_id.required_if'=> translate('validation.required_if',['attribute'=>translate('messages.item'), 'other'=>translate('messages.banner_type'), 'value'=>translate('messages.item_wise')]),
            'title.0.required'=>translate('default_data_is_required'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $banner = new Banner;
        $banner->title = $request->title[array_search('default', $request->lang)];
        $banner->type = $request->banner_type;
        $banner->zone_id = $request->zone_id;
        $banner->image = Helpers::upload('banner/', 'png', $request->file('image'));
        $banner->data = ($request->banner_type == 'store_wise')?$request->store_id:(($request->banner_type == 'item_wise')?$request->item_id:'');
        $banner->module_id = Config::get('module.current_module_id');
        $banner->default_link = $request->default_link;
        $banner->save();
        $data = [];
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->title[$index])){
                if ($key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\Banner',
                        'translationable_id' => $banner->id,
                        'locale' => $key,
                        'key' => 'title',
                        'value' => $banner->title,
                    ));
                }
            }else{
                if ($request->title[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\Banner',
                        'translationable_id' => $banner->id,
                        'locale' => $key,
                        'key' => 'title',
                        'value' => $request->title[$index],
                    ));
                }
            }
        }

        Translation::insert($data);

        return response()->json([], 200);
    }

    public function edit($id)
    {
        $banner = Banner::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.banner.edit', compact('banner'));
    }

    public function status(Request $request)
    {
        $banner = Banner::findOrFail($request->id);
        $banner->status = $request->status;
        $banner->save();
        Toastr::success(translate('messages.banner_status_updated'));
        return back();
    }

    public function featured(Request $request)
    {
        $banner = Banner::findOrFail($request->id);
        $banner->featured = $request->status;
        $banner->save();
        Toastr::success(translate('messages.banner_featured_status_updated'));
        return back();
    }

    public function update(Request $request, Banner $banner)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:191',
            'banner_type' => 'required',
            'zone_id' => 'required',
            'store_id' => 'required_if:banner_type,store_wise',
            'item_id' => 'required_if:banner_type,item_wise',
            'title.0' => 'required',
        ], [
            'zone_id.required' => translate('messages.select_a_zone'),
            'store_id.required_if'=> translate('messages.store is required when banner type is store wise'),
            'item_id.required_if'=> translate('validation.required_if',['attribute'=>translate('messages.item'), 'other'=>translate('messages.banner_type'), 'value'=>translate('messages.item_wise')]),
            'title.0.required'=>translate('default_data_is_required'),
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $banner->title = $request->title[array_search('default', $request->lang)];
        $banner->type = $request->banner_type;
        $banner->zone_id = $request->zone_id;
        $banner->image = $request->has('image') ? Helpers::update('banner/', $banner->image, 'png', $request->file('image')) : $banner->image;
        $banner->data = ($request->banner_type == 'store_wise')?$request->store_id:(($request->banner_type == 'item_wise')?$request->item_id:'');
        $banner->default_link = $request->default_link;
        $banner->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->title[$index])){
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Banner',
                            'translationable_id' => $banner->id,
                            'locale' => $key,
                            'key' => 'title'
                        ],
                        ['value' => $banner->title]
                    );
                }
            }else{

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Banner',
                            'translationable_id' => $banner->id,
                            'locale' => $key,
                            'key' => 'title'
                        ],
                        ['value' => $request->title[$index]]
                    );
                }
            }
        }

        return response()->json([], 200);
    }

    public function delete(Banner $banner)
    {
        if (Storage::disk('public')->exists('banner/' . $banner['image'])) {
            Storage::disk('public')->delete('banner/' . $banner['image']);
        }
        $banner->translations()->delete();
        $banner->delete();
        Toastr::success(translate('messages.banner_deleted_successfully'));
        return back();
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $banners=Banner::where('module_id', Config::get('module.current_module_id'))->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('title', 'like', "%{$value}%");
            }
        })->limit(50)->get();
        return response()->json([
            'view'=>view('admin-views.banner.partials._table',compact('banners'))->render(),
            'count'=>$banners->count()
        ]);
    }
}

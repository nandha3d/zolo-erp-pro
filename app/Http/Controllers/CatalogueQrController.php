<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogueQrController extends Controller
{
    public function index()
    {
        $qrs = DB::table('catalogue_qrs')->latest()->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        $tables = Table::where('is_active', true)->get();

        return view('backend.qr.index', compact('qrs', 'warehouses', 'tables'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $slug = Str::slug($request->title) . '-' . rand(100, 999);
        $menuUrl = url('/menu/' . $slug);

        DB::table('catalogue_qrs')->insert([
            'title' => $request->title,
            'warehouse_id' => $request->warehouse_id,
            'table_id' => $request->table_id,
            'slug' => $slug,
            'qr_code_path' => "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($menuUrl),
            'theme_color' => $request->theme_color ?? '#4f46e5',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Digital Catalogue QR Code generated successfully.');
    }

    public function showPublicMenu($slug)
    {
        $qr = DB::table('catalogue_qrs')->where('slug', $slug)->first();
        $categories = Category::where('is_active', true)->get();
        $products = Product::where('is_active', true)->where('is_online', true)->get();

        return view('backend.qr.public_menu', compact('qr', 'categories', 'products'));
    }
}

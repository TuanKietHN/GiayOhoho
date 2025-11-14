<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::where('user_id', $request->user()->id)->get();
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'address_line' => ['required','string','max:255','regex:/^[\p{L}0-9\s,\.-]+$/u'],
            'ward' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'district' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'city' => ['required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'country' => ['required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'postal_code' => ['nullable','string','max:20','regex:/^[A-Za-z0-9\s-]+$/'],
        ]);
        $data['user_id'] = $request->user()->id;
        $address = Address::create($data);
        return response()->json($address, 201);
    }

    public function update(Request $request, int $id)
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'address_line' => ['sometimes','required','string','max:255','regex:/^[\p{L}0-9\s,\.-]+$/u'],
            'ward' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'district' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'city' => ['sometimes','required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'country' => ['sometimes','required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'postal_code' => ['nullable','string','max:20','regex:/^[A-Za-z0-9\s-]+$/'],
        ]);
        $address->update($data);
        return response()->json($address);
    }

    public function destroy(Request $request, int $id)
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $address->delete();
        return response()->json(['message' => 'deleted']);
    }
}

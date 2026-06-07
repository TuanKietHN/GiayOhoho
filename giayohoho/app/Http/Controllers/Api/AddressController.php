<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::where('account_id', $request->user()->id)->get();
        return response()->json($addresses->map(fn(Address $address) => $this->addressPayload($address))->values());
    }

    public function store(Request $request)
    {
        $this->normalizePayload($request);
        $data = $request->validate([
            'address_line' => ['required','string','max:255','regex:/^[\p{L}0-9\s,\.-]+$/u'],
            'ward' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'district' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'city' => ['required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'country' => ['required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'postal_code' => ['nullable','string','max:20','regex:/^[A-Za-z0-9\s-]+$/'],
            'ghn_province_id' => 'nullable|integer',
            'ghn_district_id' => 'nullable|integer',
            'ghn_ward_code' => 'nullable|string|max:50',
        ]);
        $data['account_id'] = $request->user()->id;
        $address = Address::create($data);
        return response()->json($this->addressPayload($address), 201);
    }

    public function update(Request $request, int $id)
    {
        $this->normalizePayload($request);
        $address = Address::where('account_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'address_line' => ['sometimes','required','string','max:255','regex:/^[\p{L}0-9\s,\.-]+$/u'],
            'ward' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'district' => ['nullable','string','max:255','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'city' => ['sometimes','required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'country' => ['sometimes','required','string','max:100','regex:/^[\p{L}0-9\s\.-]+$/u'],
            'postal_code' => ['nullable','string','max:20','regex:/^[A-Za-z0-9\s-]+$/'],
            'ghn_province_id' => 'nullable|integer',
            'ghn_district_id' => 'nullable|integer',
            'ghn_ward_code' => 'nullable|string|max:50',
        ]);
        $address->update($data);
        return response()->json($this->addressPayload($address->refresh()));
    }

    public function destroy(Request $request, int $id)
    {
        $address = Address::where('account_id', $request->user()->id)->findOrFail($id);
        $address->delete();
        return response()->json(['message' => 'deleted']);
    }

    private function normalizePayload(Request $request): void
    {
        $request->merge([
            'address_line' => $request->input('address_line', $request->input('addressLine')),
            'postal_code' => $request->input('postal_code', $request->input('postalCode')),
            'ghn_province_id' => $request->input('ghn_province_id', $request->input('ghnProvinceId')),
            'ghn_district_id' => $request->input('ghn_district_id', $request->input('ghnDistrictId')),
            'ghn_ward_code' => $request->input('ghn_ward_code', $request->input('ghnWardCode')),
        ]);
    }

    private function addressPayload(Address $address): array
    {
        return [
            'id' => $address->id,
            'addressLine' => $address->address_line,
            'address_line' => $address->address_line,
            'ward' => $address->ward,
            'district' => $address->district,
            'city' => $address->city,
            'country' => $address->country,
            'postalCode' => $address->postal_code,
            'postal_code' => $address->postal_code,
            'ghnProvinceId' => $address->ghn_province_id,
            'ghn_province_id' => $address->ghn_province_id,
            'ghnDistrictId' => $address->ghn_district_id,
            'ghn_district_id' => $address->ghn_district_id,
            'ghnWardCode' => $address->ghn_ward_code,
            'ghn_ward_code' => $address->ghn_ward_code,
        ];
    }
}

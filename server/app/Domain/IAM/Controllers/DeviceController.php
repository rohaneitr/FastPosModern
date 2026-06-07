<?php

namespace App\Domain\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = DB::table('user_devices')
            ->where('user_id', $request->user()->id)
            ->orderBy('last_login', 'desc')
            ->get();

        return response()->json($devices);
    }

    public function block(Request $request, $id)
    {
        $device = DB::table('user_devices')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        DB::table('user_devices')->where('id', $id)->update([
            'status' => 'blocked', 
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Device blocked successfully']);
    }

    public function destroy(Request $request, $id)
    {
        $device = DB::table('user_devices')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        DB::table('user_devices')->where('id', $id)->delete();

        return response()->json(['message' => 'Device removed successfully']);
    }
}

<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Modules\Tenant\Models\Business;
use App\Modules\IAM\Models\User;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class RegistrationController extends Controller
{
    /**
     * Public endpoint for SaaS tenant self-registration.
     */
    public function registerSelf(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'domain_prefix' => 'required|string|max:64|unique:businesses,subdomain|regex:/^[a-z0-9\-]+$/',
            'admin_name'    => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => ['required', Password::defaults()],
            'plan_id'       => 'nullable|exists:plans,id', // Assuming plans table exists
        ]);

        DB::beginTransaction();

        try {
            // 1. Create the initial User (Business Admin)
            $nameParts = explode(' ', trim($request->admin_name), 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

            $user = User::create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'username'   => $request->email, // default username to email
                'email'      => $request->email,
                'password'   => Hash::make($request->password),
                'user_type'  => 'business_admin',
                'business_id' => null, // Will update after business is created
            ]);

            // 2. Fetch Plan
            $plan = null;
            if ($request->plan_id) {
                $plan = \App\Modules\Tenant\Models\Plan::find($request->plan_id);
            }

            // 3. Create the Business (Tenant)
            $business = Business::create([
                'name'      => $request->business_name,
                'subdomain' => strtolower($request->domain_prefix),
                'owner_id'  => $user->id,
                'status'    => 'pending_activation',
                'active_modules' => $plan ? $plan->enabled_modules : [],
                'trial_ends_at' => now()->addDays(30),
            ]);

            // 4. Link user to business and assign role
            $user->update(['business_id' => $business->id]);
            $user->assignRole('BusinessAdmin');

            // 4. Attach Subscription Plan (if plan_id provided)
            if ($request->plan_id) {
                DB::table('subscriptions')->insert([
                    'business_id' => $business->id,
                    'plan_id'     => $request->plan_id,
                    'status'      => 'active',
                    'start_date'  => now(),
                    'end_date'    => now()->addDays(14), // 14-day trial
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            DB::commit();

            // 5. Dispatch background job to send Welcome Email
            // Using Mail::queue directly dispatches it to the queue
            Mail::raw("Welcome to FastPOS, {$firstName}! Your tenant domain is {$business->subdomain}. Please verify your email.", function (Message $message) use ($user) {
                $message->to($user->email)
                        ->subject('Welcome to FastPOS! Verify your email');
            });

            return response()->json([
                'message' => 'Registration successful. Welcome to FastPOS!',
                'business' => $business,
                'user' => $user,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Registration failed due to a system error.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

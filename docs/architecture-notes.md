# Architecture Notes

## Tech Stack (Current Legacy System)
- **Framework:** Laravel Framework (^9.51)
- **Language:** PHP (^8.0)
- **Database ORM:** Eloquent ORM
- **Module System:** nwidart/laravel-modules (^9.0)
- **Frontend / Views:** Laravel UI (4.x), Blade templates, DataTables (yajra/laravel-datatables-oracle ^9.19)
- **Authentication:** Laravel Passport (11.6.1) for APIs, Standard Session Auth for Web
- **Authorization:** Spatie Laravel Permission (^5.5)

## Third-Party Integrations
- **Payment Gateways:** Stripe, Razorpay, Paypal, Paystack, MyFatoorah, PesaPal
- **SMS/Notifications:** Twilio, Pusher
- **Cloud Storage:** AWS S3 (league/flysystem-aws-s3-v3), Dropbox (spatie/flysystem-dropbox)
- **PDF/Printing:** Mpdf, Dompdf
- **Excel:** Maatwebsite Excel
- **Barcodes:** Milon Barcode
- **AI / Misc:** OpenAI PHP, Spatie Activitylog, Spatie Backup

## Main Application Modules (Discovered)
1. **Core / Monolith:** 
   The majority of the business logic lives in `app/Http/Controllers`. The app uses a traditional MVC approach with thick controllers handling domains like Sales, Purchases, Contacts, Products, and Business configuration.
2. **Superadmin Module:**
   Located in `Modules/Superadmin/`. Manages the SaaS aspect (businesses/tenants, subscriptions, packages).
3. **Restaurant Module:**
   Located in `app/Http/Controllers/Restaurant/`. Likely handles tables, modifiers, kitchen orders.

## APIs & Mobile Support
- Contains API endpoints in `app/Http/Controllers/Api/` and specific mobile activation controllers (`MobileActivationsController`, `MobileUserManagementController`).
- Uses Laravel Passport for token-based API authentication.

# GitHub and Packagist release instructions

## Repository
- Repository name: `laravel-vendor-payouts`
- Description: `Vendor earnings ledger and payout lifecycle management for Laravel marketplace applications.`
- URL: `https://github.com/ITMOSOFT/laravel-vendor-payouts`

## Validate and test
```bash
composer validate --strict
composer install
composer test
```

## Initial commit and tag
```bash
git init
git branch -M main
git add .
git commit -m "feat: add Laravel vendor payouts package"
git remote add origin https://github.com/ITMOSOFT/laravel-vendor-payouts.git
git push -u origin main
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

## GitHub Release
- Title: `v1.0.0 - Initial Release`
- Tag: `v1.0.0`

### Release description
Initial release of ITMOSOFT Laravel Vendor Payouts. Includes vendor earning records, available-balance calculation, transactional payout requests with earning reservations, and payout lifecycle transitions for approval, processing, paid, failed, and cancelled states. Payment-provider execution remains in the host application. Supports PHP 8.3+ and Laravel 12/13.

## Packagist
1. Create `itmosoft/laravel-vendor-payouts` on Packagist or submit the GitHub repository URL.
2. Configure the GitHub service hook on Packagist if prompted.
3. Confirm the `v1.0.0` tag is detected and package metadata is correct.

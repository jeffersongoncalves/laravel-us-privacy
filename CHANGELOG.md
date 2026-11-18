# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-11-17

First release of laravel-us-privacy, the core of a Laravel toolkit for the comprehensive privacy laws of 18 US states (Virginia, Colorado, Connecticut, Utah, Texas, Oregon, Montana, Iowa, Delaware, Nebraska, New Hampshire, New Jersey, Tennessee, Minnesota, Maryland, Indiana, Kentucky, Rhode Island).

- State rules in config: effective dates, applicability thresholds, request and appeal deadlines, sensitive data model (opt-in, opt-out, strictly necessary), universal opt-out (Global Privacy Control) dates, data protection assessments, minors and rights.
- `StateRules`: deadlines, applicability check, strictest combination for consumers of unknown state.
- `UsPrivacy::rulesForRequest()`: the visitor's state from location headers (Cloudflare) or your own resolver.
- `php artisan us-privacy:states [--consumers=...]`: the laws and which apply to you.
- Personal data inventory with sensitive types and sale / targeted advertising / profiling flags, export, deidentify strategies and k-anonymity.

The rules were compiled in November 2026; verify with counsel and override anything in `config/us-privacy.php`. California: use laravel-ccpa.

## [Unreleased]

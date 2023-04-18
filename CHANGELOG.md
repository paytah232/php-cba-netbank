# Changelog

All Notable changes to `php-cba-netbank` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [1.0.2] - 2023-04-18

### Changed

- Account/Transaction Lists now (mostly) use the built in cba api to retrieve account and transaction details.

### Removed

- Transactions now only get the 40 most recent entries, not including pending transactions (to be reintroduced soon).

## [1.0.0] - 2017-04-22

### Added
- Initial release
- Login and view accounts
- Retrieve transactions from accounts by date (Currently only retrieves 200 max, a future version will increase this. Anyone is free to do a pull request to do this, I gave up for now after many attempts)
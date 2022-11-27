# infinex-exchange/mailer
A daemon that sends e-mails to Infinex users

## Install
1. Rename `config.sample.inc.php` to `config.inc.php` and configure MariaDB and SMTP connection.
2. Install required dependencies
```
composer install
```

## Requirements
- php
- php-mysql
- composer

## Usage
You can run the mailer in debug mode to make sure everything is working properly. You will see all SMTP communication on the screen:
```
php mailer.php -d
```
On production, install mailer as a system service by adding the following command to the system autorun:
```
php mailer.php
```
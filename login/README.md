# Login
Easy prebuilt login system using Cirrus API

## Installation
Make sure you have PHP installed on your server, if you do not, follow these instrucitons on [installing PHP](https://www.php.net/manual/en/install.php)
If you already have PHP installed, you can clone the repo by running this command:
```bash
git clone https://github.com/caelen-cater/login
```
If you don't have GET, you can download the repository and unzip it.
Now that you have the source code on your server, move it to the directory of your login page.

## Setup
Get an API key for Cirrus API, learn how to get one [here](https://api.cirrus.center/docs/hc/articles/3/14/11/api-keys)
### Enable Modules
There are 3 modules, auth, logout, and token. You can enabled a module by adding your API key where it says "API_KEY" in /auth/index.php, /logout/index.php, /auth/token/index.php

## Settings
You can change where the form will redirect to after a successful login. You can change the location in /auth/callback/index.php

## Cookies
The login form will store the token in a cookie called 'auth', I reccomend you add a cookie popup on your main site to comply with GDPR

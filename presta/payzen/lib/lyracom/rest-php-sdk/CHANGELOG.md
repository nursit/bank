# Changelog

### master

- do not use json_last_error, json_last_error_msg with PHP 5.4

### 4.0.3 2019-07-31

- Throw an erro when getParsedFormAnswer failed
- getParsedFormAnswer works with escaped POST values
- checkHash works with escaped POST values

### 4.0.2 2018-12-11

- Client->checkHash() automatic hash key lookup from kr-hash-key
- Adding tests for PHP 7.3

### 4.0.1 2018-09-24

- rename composer name to lyracom/rest-php-sdk
- moved to lyra/rest-php-sdk github repository

### 4.0.0 2018-09-19

- Remove V3.0 support (fallback and sha signature checks)
- remove deprecated Client->setPrivateKey() fuonction
- Client->chechHash($key) need the signature key as first parameter
- Renamed LyraNetwork directories, classes and namespaces to Lyra
- Change docker port from 6981 to 6968

### 3.1.3 2018-05-29

- add sha256_hmac hash algorithm support
- remove travis CI tests for php 5.3.3

### 3.1.2 2018-02-08

- add setDefaultSHA256Key(), setSHA256Key() and getSHA256Key() to Client.php
- add default static configuration method to Client.php (Client::setDefault*() methods)
- add getUsername(), getPassword(), getProxyPort() and getProxyHost() to Client.php
- change docker container name from krypton-sdk to krypton-php-sdk
- adding PHP 7.2 Travis tests
- Add default version (V3) to url without any versions

### 3.1.1 2018-01-19

- Change Expcetion to LyraNetworkException in client.php

### 3.1.0 2017-11-21

New SDK version for the new 3.1 web-services.
Still compatible with 3.0 doing minor changes.

It's a release candidate.

- version is now defined in the web-service name (use V3/Charge/SDKTest instead of Charge/SDKTest)
- add $client->checkHash($hashKey) method to check POST data answer signature
- add $client->getLastCalculatedHash() get the last calculated hash by checkHash()
- add $client->getClientEndPoint() to allow to test a javascript client on a different server
- add $client->getParsedAnswer() helper to get POST data easily 
- composer ext-curl deps moved to suggest, refs #2

### V3.0.6 2017-05-15

- add setUsername and setPassword methods

### V3.0.4 2017-01-31

- Adding endpoint support
- Add Dockerfile with unzip and composer for local tests

### v3.0.3 2017-01-30

- Add file_get_contents fallback when CURL is not installed
- Add CA root certificate to fix WAMP curl + https 
- Rename namespace to LyraNetwork

### v3.0.1 2016-12-05

- Adding autoload file if you don't like composer
- More stuff in the readme file

### v3.0.0 2016-12-01

- Initial version
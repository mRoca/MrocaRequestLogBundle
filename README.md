# MrocaRequestLogBundle

An HTTP requests mock generator symfony bundle.

## Description

This bundle allows to log HTTP requests and associated responses as json files.
This generated json files can be used as API mock in order to test a front app without running the api.

## How it works ?

After each request (`Kernel::TERMINATE` event) containing the `x-generate-response-mock` header, a json file is created
containing the request and the response.

**Examples :**

> GET /categories

```json
# app/log/mocks/categories/#GET.json
{
    "request": {
        "uri": "/categories",
        "method": "GET",
        "parameters": [],
        "content": ""
    },
    "response": {
        "statusCode": 200,
        "contentType": "application/json",
        "content": {
            "@context": "/contexts/Category",
            "@id": "/categories",
            "hydra:member": [
                {"name": "foo"},
                {"name": "bar"}
            ]
        }
    }
}
```

> PUT /categories/1 {"foo": "bar"}

```json
# app/log/mocks/categories/1#PUT-9bb58.json
{
    "request": {
        "uri": "/categories/1",
        "method": "PUT",
        "parameters": [],
        "content": {
            "foo": "bar"
        }
    },
    "response": {
        "statusCode": 204,
        "contentType": "application/json",
        "content": ""
    }
}
```

## Installation

You can use [Composer](https://getcomposer.org/) to install the bundle to your project as a dev dependency :

```bash
composer require --dev mroca/request-log-bundle
```

Then, enable the bundle by updating your `app/config/AppKernel.php` file to enable the bundle:

```php
<?php
// app/config/AppKernel.php

public function registerBundles()
{
    //...
    if (in_array($this->getEnvironment(), ['dev', 'test'])) {
        //...
        $bundles[] = new Mroca\RequestLogBundle\RequestLogBundle();
    }

    return $bundles;
}
```

*If necessary*, configure the bundle to your needs (example with default values):

```yaml
# app/config/config_dev.yml

mroca_request_log:
    mocks_dir: %kernel.logs_dir%/mocks/
```

## Usage

The request & response logger is not always activated. To log a request, add the `x-generate-response-mock` header into your request :

```
GET /categories HTTP/1.1
Host: api.my.domain
x-generate-response-mock: true
```

## Commands

Some useful commands are available to manager your mocks :

### Clear all mocks

```bash
app/console mroca:response-logs:clear 
```

### Save mocks in a target directory

```bash
app/console mroca:response-logs:dump /tmp/mocksdirtarget
```

## Dev

```bash
composer install
```

**Php-cs-fixer**

```bash
vendor/bin/php-cs-fixer fix
```

**Testing**

```bash
vendor/bin/phpunit
```

## TODO

* A [Guzzle](https://github.com/csarrazi/CsaGuzzleBundle) client using this files for functionnals tests
* A [Protractor](https://angular.github.io/protractor/#/) client using this files for AngularJS e2e tests

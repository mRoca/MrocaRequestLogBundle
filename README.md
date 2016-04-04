# MrocaRequestLogBundle

An HTTP requests mock generator symfony bundle.

[![Build Status](https://travis-ci.org/mRoca/MrocaRequestLogBundle.svg)](https://travis-ci.org/mRoca/MrocaRequestLogBundle)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/24b2d907-0ddc-44d5-9ff5-437d3d9e6ad8/mini.png)](https://insight.sensiolabs.com/projects/24b2d907-0ddc-44d5-9ff5-437d3d9e6ad8)

## Description

This bundle allows to log HTTP requests and associated responses as json files.
This generated json files can be used as API mock in order to test a front app without running the api.

## How it works ?

After each request (`Kernel::TERMINATE` event) containing the `x-generate-response-mock` header, a json file is created
containing the request and the response.

**Examples :**

> GET /categories

`app/log/mocks/categories/GET__.json`

```json
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

`app/log/mocks/categories/PUT__1-a5e74.json`
```json
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

**File naming strategy**

All files are created with the following convention :

`uri/METHOD__segments{--sorted-query=string&others}{__<sha1_substr5(sortedJsonContent)>}{__<sha1_substr5(sortedPostParameters)>}.json`

*Examples* :

URL                                                         | Filename
----------------------------------------------------------- | ----------------------------------------------------
GET /                                                       | GET__.json
GET /categories                                             | categories/GET__.json
GET /categories/1                                           | categories/GET__1.json
GET /categories?search[category][]=foo                      | categories/GET__--search%5Bcategory%5D%5B%5D=foo.json
GET /categories?order[foo]=asc&order[bar]=desc              | categories/GET__--order%5Bbar%5D=desc&order%5Bfoo%5D=asc.json
GET /categories?parent=/my/iri                              | categories/GET__--parent=%2Fmy%2Firi.json
POST /categories PARAMS: foo1=bar1; foo2=bar2               | categories/POST____3e038.json
POST /categories CONTENT: {"foo1":"bar1", "foo2":"bar2"}    | categories/POST____3e038.json
PUT /categories/1 CONTENT: {"foo2":"bar2", "foo1":"bar1"}   | categories/POST__1__3e038.json

    The filenames query strings can be hashed by setting the `hash_query_params` option to `true`.
    For example, `categories/GET__--order[bar]=desc&order[foo]=asc.json` will be `categories/GET__--b0324.json`

See the [ResponseLoggerTest](/Tests/Service/ResponseLoggerTest.php#L135) file for more examples.

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
    hash_query_params: false
```

**If your are using the NelmioCorsBundle** or another CORS protection, you must add the header in the allowed ones :

```yaml
nelmio_cors:
    defaults:
        allow_headers: ['x-generate-response-mock']
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
app/console mroca:request-log:clear 
```

### Save mocks in a target directory

```bash
app/console mroca:request-log:dump /tmp/mocksdirtarget
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

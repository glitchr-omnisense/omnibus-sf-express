# omnibus/sf-express

SF Express (顺丰速运) for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): orders
with their waybills and labels (EXP_RECE_CREATE_ORDER, cloud print), tracking
(EXP_RECE_SEARCH_ROUTES) and cancellation (EXP_RECE_UPDATE_ORDER) - the open platform's signed
API. Prices come from configuration (`rates`): SF quotes by contract.

## Install and a first call

```sh
composer require omnibus/sf-express
```

No account for this first call: SF Express's answer is recorded
(`Omnibus\Testing\RecordedHttpClient` answers in place of the API), so nothing leaves the process.
With your own credentials, give the factory your HTTP client (or none: it makes its own) and drop
the recorded one.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\SfExpress\SfExpressGatewayFactory;
use Omnibus\Testing\RecordedHttpClient;

// SF Express's answer, recorded: no account, and nothing leaves the process.
$http = new RecordedHttpClient([
    'body:serviceCode=EXP_RECE_SEARCH_ROUTES' => ['apiResultCode' => 'A1000', 'apiResultData' => json_encode(['success' => true, 'errorCode' => 'S0000', 'msgData' => ['routeResps' => [
        ['mailNo' => 'SF1234567890123', 'routes' => [['acceptTime' => '2026-10-02 11:30:00', 'acceptAddress' => '北京市', 'remark' => '已签收，感谢使用顺丰', 'opCode' => '80']]],
    ]]], JSON_UNESCAPED_UNICODE)],
]);
$gateway = (new SfExpressGatewayFactory($http))->create(['partner_id' => 'GLITCH01', 'checkword' => 'cw', 'monthly_card' => '7551234567', 'sandbox' => true]);

$tracking = $gateway->track('SF1234567890123');
echo $tracking->status->value, ' - ', $tracking->latest()?->description, "\n";   // delivered - 已签收，感谢使用顺丰
```

A real call needs a SF Express account (the credentials below). **Unverified**: the request and
answer shapes come from SF Express's published documentation, and the package has not been run
against its service.

## Configuration

```php
$gateway = (new SfExpressGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `omnisense/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        sf_express:
            factory: sf_express
            options:
                partner_id: '%env(SF_PARTNER_ID)%'      # 顾客编码
                checkword: '%env(SF_CHECKWORD)%'        # 校验码
                monthly_card: '%env(SF_MONTHLY_CARD)%'  # 月结卡号
                sandbox: true
                rates: [...]
```

The service is the express type id (1 标快, 2 特快, 5 顺丰次晨, 6 顺丰即日...). Addresses: the
third street line carries the province. Shipment options: `description`, `instructions`,
`template` (the cloud print template code). No pickup points.

Credentials: an account on [SF's open platform](https://open.sf-express.com) gives the customer
code and checkword (sandbox first) and your monthly account number.

Built from SF's published open platform documentation and tested on recorded answers;
**unverified** against the sandbox until an account's keys are at hand.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by S.F. Express (SF Holding Co., Ltd.). It reaches their service on behalf of whoever runs
it, and it does not redistribute their data beyond the short excerpts recorded as test fixtures. Its
MIT licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with SF Express's terms (those
of its open platform, accepted on registration:
[open.sf-express.com](https://open.sf-express.com/)), for obtaining whatever they require (an
account, a key, a licence or subscription, an attribution or a citation, a rate limit to respect),
and for any use made of what it returns. The authors publish it free of charge, make no commercial
use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.

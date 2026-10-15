# omnibus/sf-express

SF Express (顺丰速运) for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): orders
with their waybills and labels (EXP_RECE_CREATE_ORDER, cloud print), tracking
(EXP_RECE_SEARCH_ROUTES) and cancellation (EXP_RECE_UPDATE_ORDER) - the open platform's signed
API. Prices come from configuration (`rates`): SF quotes by contract.

```php
$gateway = (new SfExpressGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `glitchr/omnibus` and `symfony/http-client`. In a
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

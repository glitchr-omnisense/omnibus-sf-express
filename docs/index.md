# omnibus/sf-express

SF Express (顺丰速运) for [omniscient/omnibus](https://github.com/glitchr-studio/omnibus): orders
with their waybills and cloud-printed labels, tracking and cancellation, through SF's open
platform. SF quotes by contract: prices come from the `rates` option. Every call is a form `POST`
to one endpoint, `https://bspgw.sf-express.com/std/service` (live) or
`https://sfapi-sbox.sf-express.com/std/service` (the sandbox, `sandbox: true`), naming the service:

| Operation | Service code |
|---|---|
| ship | `EXP_RECE_CREATE_ORDER`, then `COM_RECE_CLOUD_PRINT_WAYBILLS` |
| track | `EXP_RECE_SEARCH_ROUTES` |
| cancel | `EXP_RECE_UPDATE_ORDER` (`dealType` 2) |

## Installation

```sh
composer require omnibus/sf-express
```

Requires PHP 8.2, `omniscient/omnibus` (2.x) and `symfony/http-client` (6.4, 7 or 8); given no
HTTP client, the factory makes one with `HttpClient::create()`. In a Symfony application with
`Omnibus\Bridge\Symfony\OmnibusBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- An account on [SF's open platform](https://open.sf-express.com): the **customer code**
  (顾客编码, `partner_id`) and its **checkword** (校验码), sandbox first.
- The **monthly account number** (月结卡号) that pays for the shipments.

Each call sends `partnerID`, a random `requestID`, the `serviceCode`, a millisecond `timestamp`,
the JSON payload as `msgData` and its signature `msgDigest` =
`base64(md5(urlencode(msgData + timestamp + checkword)))` (`Omnibus\SfExpress\Api::digest()`),
with a 20-second timeout.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `partner_id` | string | - | yes | the customer code |
| `checkword` | string | - | yes | the checkword that signs every call |
| `monthly_card` | ?string | `null` | no | the monthly account that pays; not sent when unset |
| `sandbox` | bool | `false` | no | `true`: the sandbox endpoint |
| `rates` | list | - | no | the only way this gateway rates ([the rates option](https://github.com/glitchr-studio/omnibus/blob/2.x/docs/configuration.md#the-rates-option)) |

Shipment options read when booking (`Shipment::$options`):

| Option | Default | |
|---|---|---|
| `description` | `商品` | each cargo line's name |
| `instructions` | `''` | the order's remark, 100 characters |
| `template` | `fm_76130_standard_<partner_id>` | the cloud print template code |

## Capabilities

| Request | Supported |
|---|---|
| `Rating` | only with `rates` (configured prices) |
| `Shipping` | yes |
| `Tracking` | yes |
| `Pickup` | no |
| `GetSlip` | no |
| `Cancel` | yes |
| `Paperless` | no |

## Behaviour

**Answers.** An envelope whose `apiResultCode` is not `A1000` is an error; the `apiResultData` it
carries is JSON, and a result with `success` false is an error too; the result's `msgData` (else
the result) is the answer.

**Ship.** `EXP_RECE_CREATE_ORDER`, language `zh-CN`:

- the order id is the shipment's `reference`, else `omnibus-` and 12 random hexadecimal
  characters;
- one cargo line per parcel: the `description`, count 1, unit `件`, the weight in kilograms rounded
  to two decimals (at least 0.1), the parcel's `value` and currency when it has one;
- the sender (contact type 1) and the recipient (contact type 2): company, name, the phone as
  telephone and mobile, country, the **third street line as the province**, city, the first two
  street lines joined as the address, postcode, email;
- the express type `Shipment::$service` as an integer, else `1`; SF's types include 1 标快,
  2 特快, 5 顺丰次晨, 6 顺丰即日;
- payment method 1 on the `monthly_card`; the number of parcels and the total weight; a return
  route label asked for (`isReturnRoutelabel` 1); the `instructions` as remark.

The tracking number is the first waybill number. Then `COM_RECE_CLOUD_PRINT_WAYBILLS`, version
2.0, synchronous, PDF, with the `template`: the first file's base64 `content` is the label's
`content`, its `url` the label's `url`. When the print call fails, the booking still succeeds
without a document. The format is `application/pdf`; `trackingUrl` is
`https://www.sf-express.com/chn/sc/waybill/waybill-detail/<number>`. A response without a waybill
is a `CarrierException` (`SF Express issued no waybill.`). `pickupPoint` is not sent.

**Track.** `EXP_RECE_SEARCH_ROUTES` by waybill number, in Chinese when the locale starts with
`zh`, else in English. The routes of the first answer: `acceptTime` read in `Asia/Shanghai`, the
remark as description, `acceptAddress` as location, `opCode` as `code`; sorted oldest first. The
status is the latest route's, read in this order:

| `opCode` | Or the remark contains | `TrackingStatus` |
|---|---|---|
| `80` | `已签收`, `signed`, `delivered` | `DELIVERED` |
| `44` | `派件`, `out for delivery` | `OUT_FOR_DELIVERY` |
| `70`, `99` | `退回`, `return` | `RETURNED` |
| `33` | `异常`, `exception`, `unsuccessful` | `EXCEPTION` |
| `50`, `54` | `已收取`, `picked up`, `collected` | `IN_TRANSIT` |
| `30`, `31`, `36`, `604` | `已发出`, `到达`, `arrived`, `departed` | `IN_TRANSIT` |
| no code and no remark | | `UNKNOWN` |
| anything else | | `IN_TRANSIT` |

No route is ever `PENDING` or `AVAILABLE_FOR_PICKUP`.

**Cancel.** `EXP_RECE_UPDATE_ORDER` with `dealType` 2 and the waybill number, sent both as the
waybill and as `orderId`: `true` when `resStatus` is `2` (or absent). The order was created under
the shipment's reference, not under its waybill number (see "Verified, and not").

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\SfExpress\SfExpressGatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

$sf = (new SfExpressGatewayFactory(HttpClient::create()))->create([
    'partner_id' => getenv('SF_PARTNER_ID'),
    'checkword' => getenv('SF_CHECKWORD'),
    'monthly_card' => getenv('SF_MONTHLY_CARD'),
    'sandbox' => true,
]);

$shipment = new Shipment(
    new Address('张三', ['科技园南区1号', '', '广东省'], '518057', '深圳市', 'CN', company: 'Glitch Art', phone: '13800138000'),
    new Address('李四', ['朝阳区建国路1号', '', '北京市'], '100022', '北京市', 'CN', phone: '13900139000'),
    [new Parcel(1200)],
    service: '1',
    reference: 'CMD-1042',
    options: ['description' => '印刷品'],
);

$label = $sf->ship($shipment);
file_put_contents($label->trackingNumber.'.pdf', (string) $label->content);
echo $label->trackingNumber, ' ', $label->trackingUrl, "\n";

$tracking = $sf->track($label->trackingNumber, 'zh');
echo $tracking->status->value, ' ', $tracking->latest()?->location, "\n";

var_dump($sf->cancel($label->trackingNumber));
```

In Symfony:

```yaml
omnibus:
    gateways:
        sf_express:
            factory: sf_express
            options:
                partner_id: '%env(SF_PARTNER_ID)%'
                checkword: '%env(SF_CHECKWORD)%'
                monthly_card: '%env(SF_MONTHLY_CARD)%'
                sandbox: '%env(bool:SF_SANDBOX)%'
                rates:
                    - { service: '1', label: '顺丰标快', currency: CNY, countries: [CN], bands: { 1000: 1200, 5000: 2300 } }
```

## Errors

Every failure is an `Omnibus\Exception\CarrierException` whose `carrier` is `sf_express`:

- an `apiResultCode` other than `A1000`: `apiErrorMsg` (else `SF Express refused the call.`), the
  code in `carrierCode` (`A1002`...);
- a result with `success` false: its `errorMsg` (else `SF Express refused the request.`), its
  `errorCode` in `carrierCode`;
- an HTTP status of 400 or more, or an envelope that is not JSON: `SF Express answered HTTP
  <status>.`; a result that is not JSON: `SF Express answered with a result that is not JSON.`;
- a network error: `SF Express request failed: ...`;
- `SF Express issued no waybill.` when no waybill number comes back.

A failed print call after the order is created is not raised (above). A missing `partner_id` or
`checkword` is an `InvalidConfigException` when the gateway is built; `Pickup`, `GetSlip`,
`Paperless`, and `Rating` without `rates`, raise `RequestNotSupportedException` before any call.

## Verified, and not

Tested offline: `Tests/SfExpressGatewayTest.php` (the digest; an order created with its waybill
and label, the partner id, the signature, the monthly card, the express type, the province from
the third street line, the contact types; tracking with its statuses and location; the
cancellation; a refused call raised with its code) and `Tests/SfExpressContractTest.php`, the
family's conformance suite on recorded answers. The example above was run in the core's harness on
those recorded answers.

Not verified: the README and the factory mark the package **unverified** against the sandbox
until an account's keys are at hand; it is built from SF's published open platform documentation.
The cancellation sends the waybill number as `orderId`, while the order was created under the
shipment's `reference` (or a generated id): whether SF accepts it is unchecked.

## Responsibility

An independent, unofficial client, not affiliated with S.F. Express (SF Holding Co., Ltd.).
Whoever runs it complies with SF Express's terms (those of its open platform, accepted on
registration: [open.sf-express.com](https://open.sf-express.com/)): see the README's
Responsibility section.

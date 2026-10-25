<?php

namespace Omnibus\SfExpress\Tests;

use Omnibus\GatewayFactoryInterface;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\SfExpress\SfExpressGatewayFactory;
use Omnibus\Testing\GatewayContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on SF Express's recorded answers (the sandbox; unverified API shape); no rating API, so no rate without the "rates" option. */
final class SfExpressContractTest extends TestCase
{
    use GatewayContractTests;

    protected function gatewayFactory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new SfExpressGatewayFactory($http);
    }

    protected function gatewayOptions(): array
    {
        return ['partner_id' => 'GLITCH01', 'checkword' => 'cw', 'monthly_card' => '7551234567', 'sandbox' => true];
    }

    protected function contractShipment(): Shipment
    {
        return new Shipment(new Address('张三', ['福田区深南大道1000号', '', '广东省'], '518000', '深圳市', 'CN', 'Glitch Art', phone: '13800000000'), new Address('李四', ['朝阳区建国路88号', '', '北京市'], '100022', '北京市', 'CN', phone: '13900000000'), [new Parcel(1500, 30, 20, 10, 20000, 'CNY')], reference: 'ORDER-1042');
    }

    protected function contractTrackingNumber(): string
    {
        return 'SF1234567890123';
    }

    protected function recordedAnswers(): array
    {
        return [
            'body:serviceCode=EXP_RECE_CREATE_ORDER' => self::ok(['orderId' => 'ORDER-1042', 'waybillNoInfoList' => [['waybillType' => 1, 'waybillNo' => 'SF1234567890123']], 'filterResult' => 2]),
            'body:serviceCode=COM_RECE_CLOUD_PRINT_WAYBILLS' => self::ok(['obj' => ['files' => [['url' => 'https://sfapi-sbox.sf-express.com/print/SF1234567890123.pdf', 'content' => base64_encode('%PDF-1.4 sf')]]]]),
            'body:serviceCode=EXP_RECE_SEARCH_ROUTES' => self::ok(['routeResps' => [['mailNo' => 'SF1234567890123', 'routes' => [['acceptTime' => '2026-10-02 11:30:00', 'acceptAddress' => '北京市', 'remark' => '已签收，感谢使用顺丰', 'opCode' => '80'], ['acceptTime' => '2026-10-01 17:00:00', 'acceptAddress' => '深圳市', 'remark' => '顺丰速运 已收取快件', 'opCode' => '50']]]]]),
            'body:serviceCode=EXP_RECE_UPDATE_ORDER' => self::ok(['orderId' => 'SF1234567890123', 'resStatus' => '2']),
        ];
    }

    /** @param array<string, mixed> $msgData */
    private static function ok(array $msgData): array
    {
        return ['apiResultCode' => 'A1000', 'apiResultData' => json_encode(['success' => true, 'errorCode' => 'S0000', 'msgData' => $msgData], \JSON_UNESCAPED_UNICODE)];
    }
}

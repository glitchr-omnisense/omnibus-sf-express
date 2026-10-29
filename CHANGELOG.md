# Changelog

## [Unreleased]

### Fixed
- A `CarrierException` no longer carries the checkword. The message quoted the HTTP client's (the
  whole URL), the previous was the client's own exception, and every trace of the chain kept the
  arguments of the calls under it (the request's options, the `Api` with its credentials) when
  `zend.exception_ignore_args` is off: a log of the whole chain wrote them. The message masks them
  as `***`, the previous is a copy of the client's exception (the same class and code), and no
  exception `Api` throws keeps its trace's arguments (`Omnibus\Http\Redaction`).
- `cancel()` reaches the order: EXP_RECE_UPDATE_ORDER names an order by the `orderId` it was
  created under (the shipment's reference, or the id `ship()` drew), and the waybill number was
  sent in its place. The label keeps that order id as its `carrierReference`
  (omnisense/omnibus's `Label::$carrierReference`), `cancel()` sends the value given as `orderId`,
  and the waybill number is no longer sent with it.

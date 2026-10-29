# Changelog

## [Unreleased]

### Fixed
- `cancel()` reaches the order: EXP_RECE_UPDATE_ORDER names an order by the `orderId` it was
  created under (the shipment's reference, or the id `ship()` drew), and the waybill number was
  sent in its place. The label keeps that order id as its `carrierReference`
  (omnisense/omnibus's `Label::$carrierReference`), `cancel()` sends the value given as `orderId`,
  and the waybill number is no longer sent with it.

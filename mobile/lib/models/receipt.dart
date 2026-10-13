/// One amount within a payment: "1 month advance — ₱3,500".
class PaymentLine {
  final String label;

  /// A short explanation under the label, e.g. "Refundable at move-out…".
  final String? note;
  final double amount;

  const PaymentLine({required this.label, required this.amount, this.note});

  factory PaymentLine.fromJson(Map<String, dynamic> json) => PaymentLine(
    label: (json['label'] ?? '').toString(),
    note: json['note'] as String?,
    amount: (json['amount'] as num).toDouble(),
  );

  static List<PaymentLine> listFrom(dynamic json) => json is List
      ? [for (final e in json) PaymentLine.fromJson(e as Map<String, dynamic>)]
      : const [];
}

/// The receipt for a paid payment (GET /api/payments/{id}/receipt) — the same
/// details the website prints on its receipt page.
class Receipt {
  /// Zero-padded receipt number, e.g. "000012".
  final String number;
  final String business;
  final String businessAddress;
  final String billedToName;
  final String billedToEmail;
  final String? roomNumber;
  final String? property;
  final String description;

  /// What the payment is made up of: three lines for the upfront booking
  /// payment (advance, deposit, security deposit), one for anything else.
  final List<PaymentLine> lines;
  final double amount;
  final DateTime? paidAt;

  /// The payment gateway's reference, or "Recorded manually" for a walk-in.
  final String reference;

  /// Photo of the utility bill itself, when the admin attached one.
  final String? billPhotoUrl;

  const Receipt({
    required this.number,
    required this.business,
    required this.businessAddress,
    required this.billedToName,
    required this.billedToEmail,
    required this.description,
    required this.amount,
    required this.reference,
    this.lines = const [],
    this.roomNumber,
    this.property,
    this.paidAt,
    this.billPhotoUrl,
  });

  factory Receipt.fromJson(Map<String, dynamic> json) {
    final billedTo = (json['billed_to'] as Map?) ?? const {};
    final room = (json['room'] as Map?) ?? const {};
    return Receipt(
      number: json['number'].toString(),
      business: (json['business'] ?? 'A&J CITY OASIS').toString(),
      businessAddress: (json['business_address'] ?? '').toString(),
      billedToName: (billedTo['name'] ?? '').toString(),
      billedToEmail: (billedTo['email'] ?? '').toString(),
      roomNumber: room['number']?.toString(),
      property: room['property']?.toString(),
      description: (json['description'] ?? '').toString(),
      lines: PaymentLine.listFrom(json['lines']),
      amount: (json['amount'] as num).toDouble(),
      paidAt: DateTime.tryParse(json['paid_at']?.toString() ?? '')?.toLocal(),
      reference: (json['reference'] ?? '').toString(),
      billPhotoUrl: json['bill_photo_url'] as String?,
    );
  }
}

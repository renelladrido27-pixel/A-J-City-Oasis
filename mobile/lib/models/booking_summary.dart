class BookingSummary {
  final int id;
  final String status;
  final double advanceAmount;
  final double depositAmount;
  final double securityAmount;
  final double totalAmount;

  const BookingSummary({
    required this.id,
    required this.status,
    required this.advanceAmount,
    required this.depositAmount,
    required this.securityAmount,
    required this.totalAmount,
  });

  factory BookingSummary.fromJson(Map<String, dynamic> json) {
    return BookingSummary(
      id: json['id'] as int,
      status: json['status'] as String,
      advanceAmount: (json['advance_amount'] as num).toDouble(),
      depositAmount: (json['deposit_amount'] as num).toDouble(),
      securityAmount: (json['security_amount'] as num).toDouble(),
      totalAmount: (json['total_amount'] as num).toDouble(),
    );
  }
}

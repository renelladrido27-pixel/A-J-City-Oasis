class PaymentRecord {
  final int id;

  /// rent, utility, transfer_adjustment, move_out_balance…
  final String type;
  final String monthLabel;
  final double amount;
  final String status;
  final DateTime paidOn;

  const PaymentRecord({
    required this.id,
    required this.type,
    required this.monthLabel,
    required this.amount,
    required this.status,
    required this.paidOn,
  });

  factory PaymentRecord.fromJson(Map<String, dynamic> json) {
    final dueDate =
        DateTime.tryParse(json['due_date']?.toString() ?? '') ?? DateTime.now();
    return PaymentRecord(
      id: json['id'] as int,
      type: (json['type'] ?? 'rent').toString(),
      monthLabel: _monthName(dueDate),
      amount: (json['amount'] as num).toDouble(),
      status: _titleCase(json['status'] as String),
      paidOn: DateTime.tryParse(json['paid_at']?.toString() ?? '') ?? dueDate,
    );
  }

  /// "Rent", "Utility", "Transfer adjustment" — as the website labels them.
  String get typeLabel => _titleCase(type.replaceAll('_', ' '));

  static String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

  static String _monthName(DateTime d) {
    const names = [
      '',
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return names[d.month];
  }
}

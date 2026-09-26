class PaymentRecord {
  final int id;
  final String monthLabel;
  final double amount;
  final String status;
  final DateTime paidOn;

  const PaymentRecord({
    required this.id,
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
      monthLabel: _monthName(dueDate),
      amount: (json['amount'] as num).toDouble(),
      status: _titleCase(json['status'] as String),
      paidOn: DateTime.tryParse(json['paid_at']?.toString() ?? '') ?? dueDate,
    );
  }

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

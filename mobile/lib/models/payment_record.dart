import 'receipt.dart';

class PaymentRecord {
  final int id;

  /// rent, utility, transfer_adjustment, move_out_balance…
  final String type;
  final String monthLabel;
  final double amount;
  final String status;
  final DateTime dueDate;
  final DateTime paidOn;

  /// Photo of the utility bill itself, when the admin attached one.
  final String? billPhotoUrl;

  /// "Room 202 · Main Building".
  final String? room;

  /// The parts of the upfront booking payment (advance, deposit, security
  /// deposit); empty for ordinary bills.
  final List<PaymentLine> breakdown;

  const PaymentRecord({
    required this.id,
    required this.type,
    required this.monthLabel,
    required this.amount,
    required this.status,
    required this.dueDate,
    required this.paidOn,
    this.billPhotoUrl,
    this.room,
    this.breakdown = const [],
  });

  /// Still owed: not yet paid, including bills flagged overdue.
  bool get isUnpaid => status == 'Pending' || status == 'Overdue';

  factory PaymentRecord.fromJson(Map<String, dynamic> json) {
    final dueDate =
        DateTime.tryParse(json['due_date']?.toString() ?? '') ?? DateTime.now();
    return PaymentRecord(
      id: json['id'] as int,
      type: (json['type'] ?? 'rent').toString(),
      monthLabel: _monthName(dueDate),
      amount: (json['amount'] as num).toDouble(),
      status: _titleCase(json['status'] as String),
      dueDate: dueDate,
      billPhotoUrl: json['bill_photo_url'] as String?,
      room: json['room'] as String?,
      breakdown: PaymentLine.listFrom(json['breakdown']),
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

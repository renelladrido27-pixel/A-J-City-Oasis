String formatPeso(double amount) {
  final rounded = amount.round();
  final s = rounded.toString();
  final buffer = StringBuffer();
  for (int i = 0; i < s.length; i++) {
    if (i > 0 && (s.length - i) % 3 == 0) buffer.write(',');
    buffer.write(s[i]);
  }
  return '₱$buffer';
}

const _monthNames = [
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

String formatShortDate(DateTime date) =>
    '${_monthNames[date.month]} ${date.day}';

/// "Nov 1, 2026"
String formatLongDate(DateTime date) =>
    '${_monthNames[date.month]} ${date.day}, ${date.year}';

/// "₱450.75" — to the centavo, for receipts ([formatPeso] rounds to pesos).
String formatPesoExact(double amount) {
  final centavos = (amount * 100).round();
  final cents = (centavos % 100).toString().padLeft(2, '0');
  return '${formatPeso((centavos ~/ 100).toDouble())}.$cents';
}

/// "Oct 2, 2026 3:15 PM"
String formatDateTime(DateTime date) {
  final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
  final minute = date.minute.toString().padLeft(2, '0');
  return '${formatLongDate(date)} $hour:$minute ${date.hour < 12 ? 'AM' : 'PM'}';
}

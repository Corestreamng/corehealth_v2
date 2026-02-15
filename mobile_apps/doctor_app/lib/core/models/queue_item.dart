/// Queue entry from /api/mobile/doctor/queues
class QueueItem {
  final int queueId;
  final int patientId;
  final String patientName;
  final String fileNo;
  final String gender;
  final String? dob;
  final String hmoName;
  final String clinicName;
  final String status;
  final int statusCode;
  final bool vitalsTaken;
  final bool canDeliver;
  final String deliveryReason;
  final String deliveryHint;
  final String createdAt;

  QueueItem({
    required this.queueId,
    required this.patientId,
    required this.patientName,
    required this.fileNo,
    required this.gender,
    this.dob,
    required this.hmoName,
    required this.clinicName,
    required this.status,
    required this.statusCode,
    required this.vitalsTaken,
    required this.canDeliver,
    required this.deliveryReason,
    required this.deliveryHint,
    required this.createdAt,
  });

  factory QueueItem.fromJson(Map<String, dynamic> json) {
    return QueueItem(
      queueId: json['queue_id'] ?? 0,
      patientId: json['patient_id'] ?? 0,
      patientName: json['patient_name'] ?? 'Unknown',
      fileNo: json['file_no'] ?? '',
      gender: json['gender'] ?? '',
      dob: json['dob'],
      hmoName: json['hmo_name'] ?? 'N/A',
      clinicName: json['clinic_name'] ?? '',
      status: json['status'] ?? 'Unknown',
      statusCode: json['status_code'] ?? 1,
      vitalsTaken: json['vitals_taken'] == true || json['vitals_taken'] == 1,
      canDeliver: json['can_deliver'] == true,
      deliveryReason: json['delivery_reason'] ?? '',
      deliveryHint: json['delivery_hint'] ?? '',
      createdAt: json['created_at'] ?? '',
    );
  }

  /// Calculate age from dob.
  String get age {
    if (dob == null || dob!.isEmpty) return '';
    try {
      final birth = DateTime.parse(dob!);
      final now = DateTime.now();
      final years = now.year - birth.year -
          (now.month < birth.month ||
                  (now.month == birth.month && now.day < birth.day)
              ? 1
              : 0);
      if (years < 1) {
        final months = (now.year - birth.year) * 12 + now.month - birth.month;
        return '${months}mo';
      }
      return '${years}y';
    } catch (_) {
      return '';
    }
  }

  String get genderIcon => gender.toLowerCase() == 'male' ? '♂' : '♀';
}

/// Queue entry from /api/mobile/doctor/queues
class QueueItem {
  final int queueId;
  final int patientId;
  final String patientName;
  final String fileNo;
  final String gender;
  final String? dob;
  final String hmoName;
  final String hmoNo;
  final String clinicName;
  final String doctorName;
  final int statusCode;
  final String statusLabel;
  final bool vitalsTaken;
  final int? requestEntryId;
  final String priority;
  final String source;
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
    required this.hmoNo,
    required this.clinicName,
    required this.doctorName,
    required this.statusCode,
    required this.statusLabel,
    required this.vitalsTaken,
    this.requestEntryId,
    required this.priority,
    required this.source,
    required this.canDeliver,
    required this.deliveryReason,
    required this.deliveryHint,
    required this.createdAt,
  });

  factory QueueItem.fromJson(Map<String, dynamic> json) {
    return QueueItem(
      queueId: json['queue_id'] ?? 0,
      patientId: json['patient_id'] ?? 0,
      patientName: json['patient_name']?.toString() ?? 'Unknown',
      fileNo: json['file_no']?.toString() ?? '',
      gender: json['gender']?.toString() ?? '',
      dob: json['dob']?.toString(),
      hmoName: json['hmo_name']?.toString() ?? 'N/A',
      hmoNo: json['hmo_no']?.toString() ?? '',
      clinicName: json['clinic_name']?.toString() ?? '',
      doctorName: json['doctor_name']?.toString() ?? '',
      statusCode: json['status'] is int ? json['status'] : int.tryParse('${json['status']}') ?? 1,
      statusLabel: json['status_label']?.toString() ?? 'Unknown',
      vitalsTaken: json['vitals_taken'] == true || json['vitals_taken'] == 1,
      requestEntryId: json['request_entry_id'],
      priority: json['priority']?.toString() ?? 'normal',
      source: json['source']?.toString() ?? 'walk-in',
      canDeliver: json['can_deliver'] == true,
      deliveryReason: json['delivery_reason']?.toString() ?? '',
      deliveryHint: json['delivery_hint']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }

  /// Status constants matching backend QueueStatus enum.
  static const int cancelled = 0;
  static const int waiting = 1;
  static const int vitalsPending = 2;
  static const int ready = 3;
  static const int inConsultation = 4;
  static const int completed = 5;
  static const int scheduled = 6;
  static const int noShow = 7;

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

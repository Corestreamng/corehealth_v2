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
  final int? clinicId;
  final String clinicName;
  final int? staffId;
  final String doctorName;
  final int statusCode;
  final String statusLabel;
  final bool vitalsTaken;
  final int? requestEntryId;
  final int? appointmentId;
  final String? appointmentDate;
  final String? appointmentTime;
  final int rescheduleCount;
  final String priority;
  final String source;
  final bool canDeliver;
  final String deliveryReason;
  final String deliveryHint;
  final String createdAt;
  // Timer fields for tracking in-consultation elapsed time
  final String? consultationStartedAt;
  final int consultationPausedSeconds;
  final bool isPaused;
  final String? lastPausedAt;
  // Contextual next-step guidance (matches web)
  final String nextStep;

  QueueItem({
    required this.queueId,
    required this.patientId,
    required this.patientName,
    required this.fileNo,
    required this.gender,
    this.dob,
    required this.hmoName,
    required this.hmoNo,
    this.clinicId,
    required this.clinicName,
    this.staffId,
    required this.doctorName,
    required this.statusCode,
    required this.statusLabel,
    required this.vitalsTaken,
    this.requestEntryId,
    this.appointmentId,
    this.appointmentDate,
    this.appointmentTime,
    this.rescheduleCount = 0,
    required this.priority,
    required this.source,
    required this.canDeliver,
    required this.deliveryReason,
    required this.deliveryHint,
    required this.createdAt,
    this.consultationStartedAt,
    this.consultationPausedSeconds = 0,
    this.isPaused = false,
    this.lastPausedAt,
    this.nextStep = '',
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
      clinicId: json['clinic_id'],
      clinicName: json['clinic_name']?.toString() ?? '',
      staffId: json['staff_id'],
      doctorName: json['doctor_name']?.toString() ?? '',
      statusCode: json['status'] is int ? json['status'] : int.tryParse('${json['status']}') ?? 1,
      statusLabel: json['status_label']?.toString() ?? 'Unknown',
      vitalsTaken: json['vitals_taken'] == true || json['vitals_taken'] == 1,
      requestEntryId: json['request_entry_id'],
      appointmentId: json['appointment_id'],
      appointmentDate: json['appointment_date']?.toString(),
      appointmentTime: json['appointment_time']?.toString(),
      rescheduleCount: json['reschedule_count'] is int ? json['reschedule_count'] : int.tryParse('${json['reschedule_count']}') ?? 0,
      priority: json['priority']?.toString() ?? 'normal',
      source: json['source']?.toString() ?? 'walk-in',
      canDeliver: json['can_deliver'] == true,
      deliveryReason: json['delivery_reason']?.toString() ?? '',
      deliveryHint: json['delivery_hint']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
      consultationStartedAt: json['consultation_started_at']?.toString(),
      consultationPausedSeconds: json['consultation_paused_seconds'] is int ? json['consultation_paused_seconds'] : int.tryParse('${json['consultation_paused_seconds']}') ?? 0,
      isPaused: json['is_paused'] == true || json['is_paused'] == 1,
      lastPausedAt: json['last_paused_at']?.toString(),
      nextStep: json['next_step']?.toString() ?? '',
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

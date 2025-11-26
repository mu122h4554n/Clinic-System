<?php
// This file is included by appointments.php to display appointments table
$appointmentsToShow = isset($filteredAppointments) ? $filteredAppointments : $appointments;
?>

<div class="card">
    <div class="card-body">
        <?php if (empty($appointmentsToShow)): ?>
            <div class="text-center py-4">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No appointments found</h5>
                <p class="text-muted">There are no appointments to display for this filter.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointmentsToShow as $appointment): ?>
                        <tr>
                            <td>
                                <strong><?php echo formatDate($appointment['appointment_date']); ?></strong><br>
                                <small class="text-muted"><?php echo formatTime($appointment['appointment_time']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo getFullName($appointment['patient_first'], $appointment['patient_last']); ?></strong>
                                <?php if ($appointment['patient_phone']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-phone me-1"></i><?php echo $appointment['patient_phone']; ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($appointment['gender']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-<?php echo $appointment['gender'] == 'male' ? 'mars' : 'venus'; ?> me-1"></i>
                                        <?php echo ucfirst($appointment['gender']); ?>
                                        <?php if ($appointment['date_of_birth']): ?>
                                            (<?php echo date_diff(date_create($appointment['date_of_birth']), date_create('today'))->y; ?> years)
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>Dr. <?php echo getFullName($appointment['doctor_first'], $appointment['doctor_last']); ?></strong>
                            </td>
                            <td>
                                <?php echo $appointment['reason'] ? htmlspecialchars($appointment['reason']) : '<em class="text-muted">No reason specified</em>'; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    $statusColors = [
                                        'scheduled' => 'primary',
                                        'confirmed' => 'info',
                                        'in_progress' => 'warning',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    echo $statusColors[$appointment['status']] ?? 'secondary';
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
                                        
                                        <?php if (hasRole('receptionist') || hasRole('doctor')): ?>
                                            <!-- Confirm Appointment -->
                                            <?php if ($appointment['status'] == 'scheduled'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" class="btn btn-outline-info" title="Confirm">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <!-- Start Appointment (Doctor only) -->
                                            <?php if (hasRole('doctor') && ($appointment['status'] == 'confirmed' || $appointment['status'] == 'scheduled')): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="status" value="in_progress">
                                                <button type="submit" class="btn btn-outline-warning" title="Start">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <!-- Complete Appointment (Doctor only) -->
                                            <?php if (hasRole('doctor') && $appointment['status'] == 'in_progress'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-outline-success" title="Complete">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <!-- Cancel Appointment -->
                                        <?php if (hasRole('receptionist') || hasRole('doctor')): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Are you sure you want to cancel this appointment?')">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-outline-danger" title="Cancel">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                    <?php endif; ?>
                                    
                                    <!-- View Medical Record (Doctor only, for completed appointments) -->
                                    <?php if (hasRole('doctor') && $appointment['status'] == 'completed'): ?>
                                    <a href="medical-records.php?appointment_id=<?php echo $appointment['id']; ?>" 
                                       class="btn btn-outline-primary" title="View Medical Record">
                                        <i class="fas fa-file-medical"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMIS Report — MSF HMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111; background: #fff; padding: 15px; }
        .header { text-align: center; margin-bottom: 14px; border-bottom: 2px solid #111; padding-bottom: 8px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header p { font-size: 11px; color: #333; margin-top: 2px; }
        .meta { display: flex; justify-content: space-between; font-size: 9px; color: #555; margin-bottom: 10px; }
        .summary { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .summary-box { border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; font-size: 9px; }
        .summary-box strong { display: block; font-size: 14px; color: #111; }
        h2 { font-size: 13px; font-weight: bold; color: #1b5e20; margin: 14px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        h3 { font-size: 11px; font-weight: bold; color: #333; margin: 10px 0 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 10px; }
        thead tr { background: #1b5e20; color: #fff; }
        thead th { padding: 5px 6px; text-align: left; font-weight: bold; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #ddd; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        tbody td { padding: 4px 6px; vertical-align: top; }
        .footer { margin-top: 14px; font-size: 8px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
        .section { margin-bottom: 16px; }
        @media print {
            body { padding: 0; font-size: 8px; }
            .header h1 { font-size: 14px; }
            table { font-size: 8px; }
            thead th { padding: 3px 4px; }
            tbody td { padding: 2px 4px; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Metahara Sugar Factory Hospital</h1>
        <p>OPD — HMIS Report</p>
    </div>

    <div class="meta">
        <span>Period: <?php echo e(ucfirst($period)); ?> &nbsp;|&nbsp; <?php echo e($overview['start_date']); ?> to <?php echo e($overview['end_date']); ?></span>
        <span>Generated: <?php echo e(now()->format('Y-m-d H:i')); ?></span>
    </div>

    
    <div class="section">
        <h2>1. Overview</h2>
        <div class="summary">
            <div class="summary-box"><strong><?php echo e($overview['total_encounters']); ?></strong>Encounters</div>
            <div class="summary-box"><strong><?php echo e($overview['unique_patients']); ?></strong>Patients</div>
            <div class="summary-box"><strong><?php echo e($overview['lab_requests']); ?></strong>Lab Requests</div>
            <div class="summary-box"><strong><?php echo e($overview['prescriptions']); ?></strong>Prescriptions</div>
            <div class="summary-box"><strong><?php echo e($overview['referrals']); ?></strong>Referrals</div>
            <div class="summary-box"><strong><?php echo e($overview['sick_leaves']); ?></strong>Sick Leave</div>
            <div class="summary-box"><strong><?php echo e($overview['completion_rate']); ?>%</strong>Completion</div>
            <div class="summary-box"><strong><?php echo e($overview['avg_wait_minutes']); ?>m</strong>Avg Wait</div>
        </div>
    </div>

    
    <div class="section">
        <h2>2. Patient Demographics</h2>

        <?php if($demographics['by_type']->count()): ?>
        <h3>By Patient Type</h3>
        <table>
            <thead><tr><th>Type</th><th>Count</th><th>%</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $demographics['by_type']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($item['label']); ?></td>
                    <td><?php echo e($item['count']); ?></td>
                    <td><?php echo e($demographics['total_patients'] > 0 ? round(($item['count'] / $demographics['total_patients']) * 100, 1) : 0); ?>%</td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if($demographics['by_gender']->count()): ?>
        <h3>By Gender</h3>
        <table>
            <thead><tr><th>Gender</th><th>Count</th><th>%</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $demographics['by_gender']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($item['label']); ?></td>
                    <td><?php echo e($item['count']); ?></td>
                    <td><?php echo e($demographics['total_patients'] > 0 ? round(($item['count'] / $demographics['total_patients']) * 100, 1) : 0); ?>%</td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if($demographics['by_age']->count()): ?>
        <h3>By Age Group</h3>
        <table>
            <thead><tr><th>Age Group</th><th>Count</th><th>%</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $demographics['by_age']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($item['label']); ?></td>
                    <td><?php echo e($item['count']); ?></td>
                    <td><?php echo e($demographics['total_patients'] > 0 ? round(($item['count'] / $demographics['total_patients']) * 100, 1) : 0); ?>%</td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    
    <div class="section">
        <h2>3. Disease Statistics</h2>

        <?php if($disease['by_diagnosis']->count()): ?>
        <h3>Top Diagnoses</h3>
        <table>
            <thead><tr><th>#</th><th>Diagnosis</th><th>Count</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $disease['by_diagnosis']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($i + 1); ?></td><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if($disease['by_complaint']->count()): ?>
        <h3>Top Chief Complaints</h3>
        <table>
            <thead><tr><th>#</th><th>Chief Complaint</th><th>Count</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $disease['by_complaint']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($i + 1); ?></td><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    
    <div class="section">
        <h2>4. Laboratory</h2>
        <div class="summary">
            <div class="summary-box"><strong><?php echo e($laboratory['total_requests']); ?></strong>Total Requests</div>
            <div class="summary-box"><strong><?php echo e($laboratory['completed']); ?></strong>Completed</div>
            <div class="summary-box"><strong><?php echo e($laboratory['pending']); ?></strong>Pending</div>
            <div class="summary-box"><strong><?php echo e($laboratory['urgent']); ?></strong>Urgent</div>
        </div>

        <?php if($laboratory['by_test']->count()): ?>
        <h3>Most Requested Tests</h3>
        <table>
            <thead><tr><th>#</th><th>Test</th><th>Count</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $laboratory['by_test']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($i + 1); ?></td><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    
    <div class="section">
        <h2>5. Pharmacy</h2>
        <div class="summary">
            <div class="summary-box"><strong><?php echo e($pharmacy['total_prescriptions']); ?></strong>Prescriptions</div>
            <div class="summary-box"><strong><?php echo e($pharmacy['total_items']); ?></strong>Total Items</div>
            <div class="summary-box"><strong><?php echo e($pharmacy['internal']); ?></strong>Internal</div>
            <div class="summary-box"><strong><?php echo e($pharmacy['external']); ?></strong>External</div>
        </div>

        <?php if($pharmacy['by_medicine']->count()): ?>
        <h3>Most Prescribed Medicines</h3>
        <table>
            <thead><tr><th>#</th><th>Medicine</th><th>Count</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $pharmacy['by_medicine']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($i + 1); ?></td><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    
    <div class="section">
        <h2>6. Referrals</h2>
        <div class="summary">
            <div class="summary-box"><strong><?php echo e($referrals['total_referrals']); ?></strong>Total Referrals</div>
        </div>

        <?php if($referrals['by_destination']->count()): ?>
        <h3>By Destination</h3>
        <table>
            <thead><tr><th>#</th><th>Destination</th><th>Count</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $referrals['by_destination']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($i + 1); ?></td><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    
    <div class="section">
        <h2>7. Sick Leave</h2>
        <div class="summary">
            <div class="summary-box"><strong><?php echo e($sickLeave['total_sick_leaves']); ?></strong>Total</div>
            <div class="summary-box"><strong><?php echo e($sickLeave['total_days']); ?></strong>Total Days</div>
            <div class="summary-box"><strong><?php echo e($sickLeave['avg_days']); ?></strong>Avg Days</div>
        </div>

        <?php if($sickLeave['by_employee']->count()): ?>
        <h3>By Employee</h3>
        <table>
            <thead><tr><th>#</th><th>Employee</th><th>Count</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $sickLeave['by_employee']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($i + 1); ?></td><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    
    <div class="section">
        <h2>8. Completed Visits</h2>
        <div class="summary">
            <div class="summary-box"><strong><?php echo e($visits['total']); ?></strong>Total</div>
            <div class="summary-box"><strong><?php echo e($visits['completed']); ?></strong>Completed</div>
            <div class="summary-box"><strong><?php echo e($visits['transferred']); ?></strong>Transferred</div>
            <div class="summary-box"><strong><?php echo e($visits['avg_duration_mins']); ?>m</strong>Avg Duration</div>
            <div class="summary-box"><strong><?php echo e($visits['min_duration_mins']); ?>m</strong>Min</div>
            <div class="summary-box"><strong><?php echo e($visits['max_duration_mins']); ?>m</strong>Max</div>
        </div>

        <?php if($visits['by_room']->count()): ?>
        <h3>By Room</h3>
        <table>
            <thead><tr><th>Room</th><th>Total</th><th>Completed</th><th>Transferred</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $visits['by_room']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td><td><?php echo e($item['completed']); ?></td><td><?php echo e($item['transferred']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if($visits['by_doctor']->count()): ?>
        <h3>By Doctor/Nurse</h3>
        <table>
            <thead><tr><th>#</th><th>Doctor/Nurse</th><th>Count</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $visits['by_doctor']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($i + 1); ?></td><td><?php echo e($item['label']); ?></td><td><?php echo e($item['count']); ?></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="footer">MSF HMS — OPD Module &nbsp;|&nbsp; Confidential &nbsp;|&nbsp; For internal use only</div>
</body>
</html>
<?php /**PATH C:\Users\Hp\Desktop\MSF HMS\resources\views/exports/hmis-report-pdf.blade.php ENDPATH**/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MSF HMS Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #111; }
        h1 { color: #16a34a; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0fdf4; }
        .meta { color: #666; margin-bottom: 24px; }
    </style>
</head>
<body>
    <h1>MSF HMS — <?php echo e(ucfirst($report['period'])); ?> Report</h1>
    <p class="meta"><?php echo e($report['start_date']); ?> to <?php echo e($report['end_date']); ?></p>

    <h2>Summary</h2>
    <p><strong>Total Visits:</strong> <?php echo e($report['total_visits']); ?></p>

    <h2>By Patient Type</h2>
    <table>
        <thead><tr><th>Type</th><th>Count</th></tr></thead>
        <tbody>
            <?php $__currentLoopData = $report['by_patient_type']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($type); ?></td><td><?php echo e($count); ?></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <h2>Room Utilization</h2>
    <table>
        <thead><tr><th>Room</th><th>Visits</th></tr></thead>
        <tbody>
            <?php $__currentLoopData = $report['by_room']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $room => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($room); ?></td><td><?php echo e($count); ?></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</body>
</html>
<?php /**PATH C:\Users\Hp\Desktop\MSF HMS\resources\views/exports/report-pdf.blade.php ENDPATH**/ ?>
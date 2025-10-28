<?php
// download_template.php - Download CSV import template
require_once 'config.php';
requireAdmin();

// CSV Template content
$csv = "Faculty ID,Last Name,First Name,Middle Name,Username,Role,Email,Phone Number\n";
$csv .= "FAC001,Dela Cruz,Juan,P.,jdelacruz,Full Time Teacher,juan.delacruz@bpc.edu.ph,09123456789\n";
$csv .= "FAC002,Santos,Maria,R.,msantos,Registrar,maria.santos@bpc.edu.ph,09198765432\n";
$csv .= "FAC003,Reyes,Pedro,,preyes,Part Time Teacher,pedro.reyes@bpc.edu.ph,09171234567\n";

// Set headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="bpc_user_import_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Output CSV
echo $csv;
exit;
?>

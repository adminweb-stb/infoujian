<?php
// Atur kode respons HTTP ke 404 (Not Found)
http_response_code(404);

// Sertakan halaman 404.html dari root
include __DIR__ . '/404.html';

// Hentikan eksekusi skrip
exit;
?>

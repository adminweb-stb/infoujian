Write-Host "🔄 Membersihkan proses Node.js lama..." -ForegroundColor Yellow
taskkill /IM node.exe /F 2>$null
Start-Sleep -Seconds 2

Write-Host "✅ Port 3000 & 5173 bebas. Menjalankan server..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit -Command `"cd 'C:\laragon\www\ujian'; npm start`"" -WindowStyle Normal
Start-Sleep -Seconds 2
Start-Process powershell -ArgumentList "-NoExit -Command `"cd 'C:\laragon\www\ujian'; npm run dev`"" -WindowStyle Normal

Write-Host "🚀 Server Express (3000) dan Vite (5173) sudah berjalan!" -ForegroundColor Cyan
Write-Host "   Buka: http://localhost:5173" -ForegroundColor White

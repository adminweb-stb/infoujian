import UAParser from 'ua-parser-js';
import axios from 'axios';

/**
 * Advanced Forensic Logger for Node.js
 * Ported from logger.php
 */
export const runVisitorLogger = async (db, req, body = {}) => {
  try {
    const uaString = req.headers['user-agent'] || 'Unknown';
    const parser = new UAParser(uaString);
    const result = parser.getResult();

    // 1. Capture Identity
    let ip = req.headers['x-forwarded-for'] || req.socket.remoteAddress || '0.0.0.0';
    if (ip.includes(',')) ip = ip.split(',')[0].trim();
    if (ip === '::1') ip = '127.0.0.1';

    const os = `${result.os.name || 'Unknown'} ${result.os.version || ''}`.trim();
    const brand = result.device.vendor || (os.includes('Windows') ? 'PC / Laptop' : 'Generic Device');
    const deviceType = result.device.type || 'Desktop';
    const isBot = /bot|crawl|slurp|spider|mediapartners/i.test(uaString) ? 1 : 0;

    // 2. Geo-IP (Cache using simple session-like logic or just fetch)
    let country = "Local/Private";
    let city = "";
    let isp = "";

    if (ip !== '127.0.0.1' && !ip.startsWith('192.168.') && !ip.startsWith('10.')) {
      try {
        const geoRes = await axios.get(`http://ip-api.com/json/${ip}?fields=status,country,city,isp`, { timeout: 1500 });
        if (geoRes.data && geoRes.data.status === 'success') {
          country = geoRes.data.country;
          city = geoRes.data.city;
          isp = geoRes.data.isp;
        }
      } catch (e) {
        console.error("Geo-IP Error:", e.message);
      }
    }

    // 3. Determine Context
    const examType = body.exam_type || 'unknown';
    const semester = parseInt(body.semester || 0);
    const action = body.action || 'page_load';
    const context = body.context || '';
    const referrer = req.headers['referer'] || req.headers['referrer'] || '';
    const resolution = body.resolution || '';
    const path = body.path || req.headers['referer'] || '/';
    const pageTitle = body.page_title || '';

    // 4. Log to DB
    const query = `
      INSERT INTO visitor_logs 
      (ip_address, user_agent, os, brand, country, city, isp, device_type, exam_type, semester, action, path, page_title, is_bot, context, referrer, resolution) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;
    
    const values = [
      ip, uaString, os, brand, country, city, isp, deviceType, 
      examType, semester, action, path, pageTitle, isBot, context, referrer, resolution
    ];

    await db.execute(query, values);
    return true;
  } catch (error) {
    console.error("Logger Error:", error);
    return false;
  }
};

/**
 * Check if an IP is blacklisted
 */
export const checkBlacklist = async (db, ip) => {
  try {
    const [rows] = await db.execute('SELECT * FROM blacklist_ips WHERE ip_address = ?', [ip]);
    return rows.length > 0 ? rows[0] : null;
  } catch (error) {
    console.error("Blacklist Check Error:", error);
    return null;
  }
};

/**
 * Add an IP to the blacklist with Geo-IP lookup
 */
export const addToBlacklist = async (db, ip, reason, threatLevel = 'medium') => {
  try {
    // 1. Get Geo-IP Info
    let country = "Unknown", city = "Unknown", isp = "Unknown";
    try {
      const geoRes = await axios.get(`http://ip-api.com/json/${ip}?fields=status,country,city,isp`, { timeout: 2000 });
      if (geoRes.data && geoRes.data.status === 'success') {
        country = geoRes.data.country;
        city = geoRes.data.city;
        isp = geoRes.data.isp;
      }
    } catch (e) {}

    // 2. Insert or Update
    const query = `
      INSERT INTO blacklist_ips (ip_address, reason, country, city, isp, threat_level)
      VALUES (?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE 
        reason = CONCAT(reason, ' | ', VALUES(reason)),
        threat_level = VALUES(threat_level)
    `;
    await db.execute(query, [ip, reason, country, city, isp, threatLevel]);
    console.log(`[BLACKLISTED] IP: ${ip} | Reason: ${reason} | Origin: ${city}, ${country}`);
    return true;
  } catch (error) {
    console.error("Add to Blacklist Error:", error);
    return false;
  }
};

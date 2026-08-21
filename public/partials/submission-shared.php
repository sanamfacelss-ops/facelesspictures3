<?php
/**
 * Shared partial — included by actor.php, director.php, writer.php
 * Provides:
 *   - Success modal markup (x-data tied to window.submissionResult)
 *   - PDF download function (client-side, no library needed)
 *   - submissionForm() Alpine component (video required, success modal trigger)
 *
 * Usage: require at BOTTOM of each page, just before </body>
 */
?>

<!-- ══════════════════════════════════════════════════════════════
     SUCCESS MODAL  (shared across all audition pages)
     ══════════════════════════════════════════════════════════════ -->
<div id="successModal"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(10,14,26,.88);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:1.5rem;">
  <div style="background:#161C2D;border:1px solid #1F2840;border-radius:20px;width:100%;max-width:480px;padding:2.5rem;position:relative;text-align:center;box-shadow:0 40px 80px rgba(0,0,0,.6);">

    <!-- Close -->
    <button onclick="closeSuccessModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:50%;width:32px;height:32px;color:#8B92A5;cursor:pointer;font-size:16px;line-height:1;display:flex;align-items:center;justify-content:center;">✕</button>

    <!-- Checkmark -->
    <div style="width:72px;height:72px;background:rgba(34,197,94,.1);border:2px solid rgba(34,197,94,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
      <svg width="32" height="32" fill="none" stroke="#22c55e" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    </div>

    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:.02em;color:#F0EBE0;margin-bottom:.5rem;">SUBMISSION RECEIVED!</h2>
    <p id="modalSubtitle" style="color:#8B92A5;font-size:.9rem;line-height:1.6;margin-bottom:1.75rem;"></p>

    <!-- Details grid -->
    <div style="background:#0A0E1A;border:1px solid #1F2840;border-radius:10px;padding:1rem;margin-bottom:1.75rem;text-align:left;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <div>
          <p style="color:#8B92A5;font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Name</p>
          <p id="modalName" style="color:#F0EBE0;font-size:.875rem;font-weight:500;"></p>
        </div>
        <div>
          <p style="color:#8B92A5;font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Role</p>
          <p id="modalRole" style="color:#E6A817;font-size:.875rem;font-weight:600;text-transform:capitalize;"></p>
        </div>
        <div style="grid-column:1/-1;">
          <p style="color:#8B92A5;font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.2rem;">Email on file</p>
          <p id="modalEmail" style="color:#F0EBE0;font-size:.875rem;"></p>
        </div>
      </div>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;flex-direction:column;gap:.75rem;">
      <button id="modalPdfBtn"
        onclick="downloadBriefPDF()"
        style="display:flex;align-items:center;justify-content:center;gap:.5rem;background:#E6A817;color:#0A0E1A;font-weight:700;border:none;border-radius:8px;padding:.75rem 1.5rem;font-size:.9rem;cursor:pointer;width:100%;transition:background .2s;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Download Brief as PDF
      </button>
      <button onclick="closeSuccessModal()"
        style="background:transparent;border:1px solid #1F2840;color:#8B92A5;border-radius:8px;padding:.65rem 1.5rem;font-size:.875rem;cursor:pointer;width:100%;transition:border-color .2s;">
        Close
      </button>
    </div>

  </div>
</div>

<script>
/* ── brief content injected per-page ─────────────────────────── */
window._briefForPDF = window._briefForPDF || { title: '', content: '', auditionType: '' };

/* ── show / hide modal ─────────────────────────────────────────── */
function showSuccessModal(data) {
    const role = (data.role || '').toLowerCase();
    
    // Get role-specific text from PHP (injected per page)
    const messages = window._submissionMessages || {};
    
    document.getElementById('modalName').textContent    = data.name        || '';
    document.getElementById('modalEmail').textContent   = data.email       || '';
    document.getElementById('modalRole').textContent    = (data.role || '') + ' — ' + (data.audition_type || '');
    
    // Use role-specific heading and message
    const heading = messages[role + '_success_heading'] || 'SUBMISSION RECEIVED!';
    const message = messages[role + '_success_message'] || "Your video is in the queue for AI review and will be published to YouTube once approved. We'll be in touch at " + (data.email || 'your email') + '.';
    const pdfButton = messages[role + '_success_pdf_button'] || 'Download Brief as PDF';
    
    document.querySelector('#successModal h2').textContent = heading;
    document.getElementById('modalSubtitle').textContent = message;
    document.getElementById('modalPdfBtn').querySelector('span') ? 
        document.getElementById('modalPdfBtn').querySelector('span').textContent = pdfButton :
        document.getElementById('modalPdfBtn').textContent = pdfButton;
    
    document.getElementById('successModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSuccessModal(); });

/* ── PDF download (pure JS — no library) ──────────────────────── */
function downloadBriefPDF() {
    const brief = window._briefForPDF;

    // Build a minimal printable HTML document
    const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>${brief.auditionType} Brief — Faceless Pictures 3</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Georgia, serif; color: #111; background: #fff; padding: 48px; max-width: 680px; margin: 0 auto; }
  .logo { font-size: 11px; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: #888; margin-bottom: 32px; }
  h1 { font-size: 28px; font-weight: 700; margin-bottom: 6px; letter-spacing: -.5px; }
  .badge { display: inline-block; background: #E6A817; color: #000; font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; padding: 4px 10px; border-radius: 4px; margin-bottom: 24px; }
  .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: #888; margin-bottom: 10px; }
  .content { font-size: 15px; line-height: 1.75; color: #222; background: #f9f7f4; border: 1px solid #e0dbd4; border-radius: 8px; padding: 20px 24px; white-space: pre-wrap; }
  .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 11px; color: #aaa; display: flex; justify-content: space-between; }
</style>
</head>
<body>
  <div class="logo">Faceless Pictures 3 &mdash; Audition Brief</div>
  <h1>${brief.title}</h1>
  <span class="badge">${brief.auditionType}</span>
  <p class="label">The Brief</p>
  <div class="content">${escapeHtml(brief.content)}</div>
  <div class="footer">
    <span>facelesspictures.com</span>
    <span>No face. Just talent.</span>
  </div>
</body>
</html>`;

    const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const win  = window.open(url, '_blank',
        'width=750,height=900,menubar=no,toolbar=no,location=no,status=no');

    if (win) {
        win.onload = () => {
            setTimeout(() => { win.print(); }, 400);
        };
    } else {
        // Fallback: direct download as HTML (user can print → Save as PDF)
        const a = document.createElement('a');
        a.href = url;
        a.download = brief.auditionType.replace(/\s+/g, '_') + '_Brief.html';
        a.click();
    }
    setTimeout(() => URL.revokeObjectURL(url), 3000);
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ── Alpine submissionForm — VIDEO REQUIRED, triggers modal ────── */
function submissionForm(role, auditionType, briefTitle, briefContent) {
    // Store brief for PDF generation
    window._briefForPDF = { title: briefTitle, auditionType: auditionType, content: briefContent };

    return {
        role, auditionType,
        form: { name:'', email:'', phone:'', notes:'' },
        file: null, dragOver: false,
        loading: false, uploading: false, progress: 0,
        errors: [], success: '',
        selectedScript: null, selectedScriptTitle: '',

        handleFile(e) { this.file = e.target.files[0] || null; },
        handleDrop(e) {
            this.dragOver = false;
            const f = e.dataTransfer.files[0];
            if (f) this.file = f;
        },

        // Update brief for PDF whenever audition type changes (multi-card pages)
        updateBrief(title, content) {
            window._briefForPDF = { title, auditionType: this.auditionType, content };
        },

        async submit() {
            this.errors = [];

            // Client-side video required check
            if (!this.file) {
                this.errors = ['A video file is required. Please select your video before submitting.'];
                return;
            }

            // Basic MIME check client-side
            const allowed = ['video/mp4','video/quicktime','video/x-msvideo','video/webm','video/mpeg','video/avi'];
            if (this.file.type && !allowed.includes(this.file.type) && !this.file.name.match(/\.(mp4|mov|avi|webm|mpeg)$/i)) {
                this.errors = ['Only video files are accepted (MP4, MOV, AVI, WEBM).'];
                return;
            }

            this.loading = true; this.uploading = true; this.progress = 0;

            const fd = new FormData();
            fd.append('role',          this.role);
            fd.append('audition_type', this.auditionType);
            fd.append('name',          this.form.name);
            fd.append('email',         this.form.email);
            fd.append('phone',         this.form.phone);
            fd.append('notes',         this.form.notes);
            if (this.selectedScript) fd.append('script_id', this.selectedScript);
            fd.append('file', this.file);

            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = e => {
                if (e.lengthComputable) this.progress = Math.round(e.loaded / e.total * 100);
            };
            xhr.onload = () => {
                this.loading = false; this.uploading = false;
                try {
                    const r = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && r.success) {
                        // Reset form
                        this.form = { name:'', email:'', phone:'', notes:'' };
                        this.file = null;
                        this.selectedScript = null;
                        this.errors = [];
                        // Show success modal
                        showSuccessModal(r);
                    } else {
                        this.errors = r.errors || [r.error || 'Submission failed. Please try again.'];
                    }
                } catch(e) {
                    this.errors = ['Server error. Please try again.'];
                }
            };
            xhr.onerror = () => {
                this.loading = false; this.uploading = false;
                this.errors = ['Network error. Please check your connection and try again.'];
            };
            xhr.open('POST', '/api/submit');
            xhr.send(fd);
        }
    };
}
</script>

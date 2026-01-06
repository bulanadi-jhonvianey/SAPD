/* =========================================
   1. GLOBAL THEME LOGIC
   ========================================= */
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('themeToggle');
    const icon = toggleBtn ? toggleBtn.querySelector('i') : null;
    const html = document.documentElement;

    // Helper to set icon
    const updateIcon = (theme) => {
        if (!icon) return;
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    };

    // Initialize Icon based on current attribute
    updateIcon(html.getAttribute('data-bs-theme'));

    // Toggle Event
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const current = html.getAttribute('data-bs-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('appTheme', newTheme);
            updateIcon(newTheme);
        });
    }
});

/* =========================================
   2. LOGIN PAGE LOGIC
   ========================================= */
function togglePassword() {
    const passwordInput = document.getElementById('passwordInput');
    const icon = document.getElementById('togglePassword');
    if (!passwordInput) return;

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.setAttribute('name', 'eye-off-outline');
    } else {
        passwordInput.type = 'password';
        icon.setAttribute('name', 'eye-outline');
    }
}

/* =========================================
   3. DASHBOARD LOGIC
   ========================================= */
document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('miniCalendar');
    
    // Only run if calendar element exists (Dashboard page)
    if (calendarEl) {
        // Init FullCalendar
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: '100%',
            headerToolbar: { left: 'prev,next', center: 'title', right: '' },
            selectable: true,
            dateClick: function(info) {
                document.getElementById('eventStart').value = info.dateStr;
                document.getElementById('eventTime').value = '';
                var modal = new bootstrap.Modal(document.getElementById('eventModal'));
                modal.show();
            },
            // Mock Events for visual demo
            events: [
                { title: 'Meeting', start: new Date().toISOString().split('T')[0] }
            ]
        });
        calendar.render();
        window.calendarInstance = calendar;
    }

    // Handle Search Form
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const term = document.getElementById('searchInput').value.toLowerCase().trim();
            // Basic routing logic
            if(term.includes('incident')) window.location.href = 'forms.php?type=incident';
            else if(term.includes('permit')) window.location.href = 'permit_card.php';
            else alert('No matching module found for: ' + term);
        });
    }
});

/* =========================================
   4. PERMIT CARD GENERATOR LOGIC
   ========================================= */
// Global QR Object
let qrcodeObj = null;

document.addEventListener('DOMContentLoaded', () => {
    const qrContainer = document.getElementById("qrcode");
    if (qrContainer) {
        qrcodeObj = new QRCode(qrContainer, { width: 100, height: 100 });
        // Init system on load
        updateSystem();
        generateQR();
        updateCard();
    }
});

// Toggle Layout Settings Inputs
function toggleSettings() {
    const state = !document.getElementById('enableSettings').checked;
    document.querySelectorAll('.settings-input').forEach(i => i.disabled = state);
}

// Update CSS Variables based on Inputs
function updateSystem() {
    const w = document.getElementById('cardWidth').value || 110;
    const h = document.getElementById('cardHeight').value || 70;
    const root = document.documentElement.style;

    root.setProperty('--card-width', w + 'mm');
    root.setProperty('--card-height', h + 'mm');
    
    root.setProperty('--name-width', (document.getElementById('nameWidth').value || 48) + '%');
    root.setProperty('--name-top', (document.getElementById('nameTop').value || 48) + '%');
    root.setProperty('--name-font-size', (document.getElementById('nameFontSize').value || 10) + 'pt');

    root.setProperty('--dept-width', (document.getElementById('deptWidth').value || 40) + '%');
    root.setProperty('--dept-top', (document.getElementById('deptTop').value || 58) + '%');
    root.setProperty('--dept-font-size', (document.getElementById('deptFontSize').value || 10) + 'pt');

    const dimsDisplay = document.getElementById('dimsDisplay');
    if(dimsDisplay) dimsDisplay.innerText = w + 'mm x ' + h + 'mm';
}

// Image Preview
function previewImage() {
    const file = document.getElementById('inputPhoto').files[0];
    if(file) { 
        const r = new FileReader(); 
        r.onload = e => document.getElementById('previewPhoto').src = e.target.result; 
        r.readAsDataURL(file); 
    }
}

// Delete Photo
function deletePhoto() {
    document.getElementById('inputPhoto').value = "";
    document.getElementById('previewPhoto').src = "https://via.placeholder.com/150";
}

// Generate QR Code
function generateQR() {
    if(qrcodeObj) {
        qrcodeObj.clear(); 
        qrcodeObj.makeCode(document.getElementById('inputFBLink').value || "https://facebook.com");
    }
}

// Update Text Content
function updateCard() {
    const setTxt = (id, val) => {
        const el = document.getElementById(id);
        if(el) el.innerText = val.toUpperCase();
    };
    
    setTxt('previewName', document.getElementById('inputName').value || "JUAN DELA CRUZ");
    setTxt('previewRole', document.getElementById('inputRole').value || "SCITE");
    setTxt('previewPlate', document.getElementById('inputPlate').value);
    setTxt('previewAcadYear', document.getElementById('inputAcadYear').value);
}

// Export PDF
function downloadPDF() {
    const card = document.getElementById('permitCard');
    const w = parseFloat(document.getElementById('cardWidth').value);
    const h = parseFloat(document.getElementById('cardHeight').value);
    
    // Force white background for capture
    html2canvas(card, { scale: 4, backgroundColor: "#ffffff" }).then(canvas => {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [w, h] });
        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, w, h);
        pdf.save('Permit.pdf');
    });
}

// Export Word
function downloadWord() {
    const card = document.getElementById('permitCard');
    html2canvas(card, { scale: 3, backgroundColor: "#ffffff" }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'Permit.doc';
        link.href = URL.createObjectURL(new Blob(['<html><body><img src="'+canvas.toDataURL('image/png')+'"></body></html>'], {type:'application/msword'}));
        link.click();
    });
}
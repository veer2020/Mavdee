function initImageUploader(index) {
    var zone       = document.getElementById('dropArea' + index);
    var preview    = document.getElementById('preview' + index);
    if (!zone || !preview) return;

    var fileInput   = zone.querySelector('.file-input');
    var previewImg  = preview.querySelector('.preview-img');
    var hiddenInput = document.getElementById('imageUrl' + index);
    var removeBtn   = preview.querySelector('.remove-img');

    zone.addEventListener('click', function (e) {
        if (e.target !== fileInput) fileInput.click();
    });
    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave', function () {
        zone.classList.remove('dragover');
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        var file = e.dataTransfer.files[0];
        if (file) uploadFile(file, index, zone, preview, previewImg, hiddenInput);
    });
    fileInput.addEventListener('change', function () {
        var file = fileInput.files[0];
        if (file) uploadFile(file, index, zone, preview, previewImg, hiddenInput);
    });
    removeBtn.addEventListener('click', function () {
        hiddenInput.value = '';
        previewImg.src    = '';
        preview.style.display = 'none';
        zone.style.display    = 'flex';
        fileInput.value = '';
    });
}

async function uploadFile(file, index, zone, preview, previewImg, hiddenInput) {
    var originalHtml = zone.innerHTML;
    zone.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Uploading\u2026</span></div>'
                   + '<p class="mt-2 text-muted small">Uploading\u2026</p>';

    var csrfInput = document.querySelector('input[name="csrf_token"]');
    var formData  = new FormData();
    formData.append('file', file);
    formData.append('csrf_token', csrfInput ? csrfInput.value : '');

    try {
        var response = await fetch('upload_image.php', { method: 'POST', body: formData });
        var data     = await response.json();
        if (data.success) {
            hiddenInput.value     = data.url;
            previewImg.src        = data.url;
            preview.style.display = 'block';
            zone.style.display    = 'none';
            zone.innerHTML        = originalHtml;
        } else {
            zone.innerHTML = originalHtml;
            var errP = document.createElement('p');
            errP.className = 'text-danger small mt-2';
            errP.textContent = data.error || 'Upload failed.';
            zone.appendChild(errP);
        }
    } catch (err) {
        zone.innerHTML = originalHtml;
        var errP = document.createElement('p');
        errP.className = 'text-danger small mt-2';
        errP.textContent = 'Upload failed. Please try again.';
        zone.appendChild(errP);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initImageUploader(1);
    initImageUploader(2);
    initImageUploader(3);
    initImageUploader(4);
});

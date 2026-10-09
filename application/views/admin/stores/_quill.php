<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Quill === 'undefined') return;
  var box = document.getElementById('quill-store-description');
  var input = document.getElementById('storeDescription');
  if (!box || !input) return;
  var quill = new Quill(box, {
    theme: 'snow',
    modules: {
      toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link'],
        ['clean']
      ]
    }
  });
  if (input.value && input.value.trim()) {
    quill.clipboard.dangerouslyPasteHTML(input.value);
  }
  var form = box.closest('form');
  if (form) {
    form.addEventListener('submit', function () {
      var ed = box.querySelector('.ql-editor');
      input.value = ed ? ed.innerHTML : '';
    });
  }
});
</script>

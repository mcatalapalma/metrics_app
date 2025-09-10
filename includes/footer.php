  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const btnSidebar = document.getElementById('btnSidebar');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
if(btnSidebar){
  btnSidebar.addEventListener('click', ()=>{ sidebar.classList.toggle('show'); overlay.classList.toggle('show'); });
}
if(overlay){
  overlay.addEventListener('click', ()=>{ sidebar.classList.remove('show'); overlay.classList.remove('show'); });
}
</script>
</body>
</html>

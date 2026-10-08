<script>
(function(){
  /* ----------------------------------------
     Utility helpers
  ---------------------------------------- */
  function parseNum(s){
    if (s === null || typeof s === 'undefined') return 0;
    if (typeof s === 'number') return s;
    s = String(s).trim();
    if (s === '') return 0;
    s = s.replace(/[^0-9\.\-]/g, '');
    var n = parseFloat(s);
    return isFinite(n) ? n : 0;
  }
  function fmt(n){
    return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  }

  const COLS = ['A','B','C','D','E','F','G','H','J','K','L','M','N','P','Q','R','S','T','U','V'];

  /* ----------------------------------------
     Toast Notifications (Top-Right Corner)
  ---------------------------------------- */
  function showToast(message, color = '#28a745', duration = 2500) {
    // create toast container if not present
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      Object.assign(container.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        zIndex: 9999,
        display: 'flex',
        flexDirection: 'column',
        gap: '10px'
      });
      document.body.appendChild(container);
    }

    // create toast element
    const toast = document.createElement('div');
    Object.assign(toast.style, {
      backgroundColor: color,
      color: '#fff',
      padding: '12px 18px',
      borderRadius: '6px',
      boxShadow: '0 3px 10px rgba(0,0,0,0.25)',
      fontSize: '15px',
      fontWeight: '500',
      opacity: '0',
      transform: 'translateX(30px)',
      transition: 'all 0.5s ease',
      maxWidth: '320px',
      wordWrap: 'break-word'
    });
    toast.textContent = message;
    container.appendChild(toast);

    // animate in
    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateX(0)';
    });

    // remove after duration
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(30px)';
      setTimeout(() => toast.remove(), 500);
    }, duration);
  }

  /* ----------------------------------------
     Totals + Row Calculations
  ---------------------------------------- */
  function calculateTotalsForTable(table){
    var totals = {};
    COLS.forEach(c => totals[c] = 0);

    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function(row){
      COLS.forEach(function(c){
        var inp = row.querySelector('input[data-field="'+c+'"]') || row.querySelector('[name="'+c+'[]"]');
        if (inp){
          totals[c] += parseNum(inp.value || inp.textContent || inp.innerText);
        }
      });
    });

    COLS.forEach(function(c){
      var el = document.getElementById('total_' + c);
      if (el) el.textContent = fmt(totals[c]);
    });

    return totals;
  }

  function initRowCalc(row){
    if (!row) return;
    function recalcRow(){
      var get = f => parseNum((row.querySelector('[data-field="'+f+'"]') || row.querySelector('[name="'+f+'[]"]') || {}).value || 0);
      var set = (f, val) => {
        var el = row.querySelector('[data-field="'+f+'"]');
        if (el) el.value = fmt(val);
      };

      var A=get('A'),B=get('B'),C=get('C'),D=get('D'),H=get('H'),J=get('J'),L=get('L'),M=get('M'),Q=get('Q'),R=get('R'),S=get('S'),T=get('T'),U=get('U');
      set('E', C+D);
      set('G', A-B-C);
      set('K', H+J);
      set('N', H+L+M);
      set('P', A-(H+L+M));
      set('V', (A-(H+L+M)) - Q - R - S - T - U);
    }

    var inputs = row.querySelectorAll('input.calc, input[data-field], textarea');
    inputs.forEach(function(inp){
      if (inp.__recalc_handler__) inp.removeEventListener('input', inp.__recalc_handler__);
      inp.__recalc_handler__ = function(){ recalcRow(); updateTotalsDebounced(); };
      inp.addEventListener('input', inp.__recalc_handler__);

      if (inp.__blur_handler__) inp.removeEventListener('blur', inp.__blur_handler__);
      inp.__blur_handler__ = function(){ if (inp.tagName==='INPUT') inp.value=fmt(parseNum(inp.value)); recalcRow(); updateTotalsDebounced(); };
      inp.addEventListener('blur', inp.__blur_handler__);
    });

    // autosize textarea
    var txt = row.querySelector('textarea[name="description[]"]');
    if (txt && !txt.__autosize) {
      txt.__autosize = true;
      txt.addEventListener('input', function(){ txt.style.height='auto'; txt.style.height=txt.scrollHeight+'px'; });
      setTimeout(() => { txt.style.height=txt.scrollHeight+'px'; }, 10);
    }

    recalcRow();
  }

  function initAllRows(table){
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(initRowCalc);
    renumberBills(table);
    updateTotalsForTable(table);
  }

  function renumberBills(table){
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function(r, idx){
      var bill = r.querySelector('input[name="bill_no[]"]');
      if (bill) bill.value = idx + 1;
    });
  }

  function updateTotalsForTable(table){ calculateTotalsForTable(table); }

  var totalsTimer=null;
  function updateTotalsDebounced(){
    clearTimeout(totalsTimer);
    totalsTimer=setTimeout(function(){
      var table=document.querySelector('#revisionTable');
      if (table) updateTotalsForTable(table);
    },80);
  }

  /* ----------------------------------------
     Main Initialization (when table is loaded)
  ---------------------------------------- */
  window.initRevisionTable=function(container,editableMode){
    if (!container) container=document.getElementById('revisionContainer');
    if (!container) return;
    var table=container.querySelector('#revisionTable');
    if (!table) return;

    initAllRows(table);

    // Add Row
    var addBtn=container.querySelector('#addRowBtn');
    if(addBtn){
      addBtn.onclick=function(){
        var tbody=table.querySelector('tbody');
        var last=tbody.querySelector('tr:last-of-type');
        var newRow;
        if(last){
          newRow=last.cloneNode(true);
          newRow.querySelectorAll('input').forEach(inp=>{inp.value='';});
          newRow.querySelectorAll('textarea').forEach(t=>{t.value='';t.style.height='';});
        } else {
          newRow=document.createElement('tr');
          var html='<td><input type="text" name="bill_no[]" readonly></td>';
          html+='<td><textarea name="description[]" rows="1"></textarea></td>';
          COLS.forEach(c=>{
            var readonly=(['E','G','K','N','P','V'].includes(c))?'readonly':'';
            var cls=readonly?'':'calc';
            html+='<td><input type="text" name="'+c+'[]" data-field="'+c+'" class="'+cls+'" '+readonly+'></td>';
          });
          html+='<td><button type="button" class="deleteRowBtn">🗑️</button></td>';
          newRow.innerHTML=html;
        }
        tbody.appendChild(newRow);
        initRowCalc(newRow);
        renumberBills(table);
        updateTotalsDebounced();
      };
    }

    // Save
    var saveBtn=container.querySelector('#saveRevisionBtn');
    if(saveBtn){
      saveBtn.onclick=function(){
        saveBtn.disabled=true;
        showToast('💾 Saving revision...', '#007bff', 1500);
        saveRevision(table).finally(()=>saveBtn.disabled=false);
      };
    }

    // Delete row
    table.onclick=function(e){
      var btn=e.target.closest('.deleteRowBtn');
      if(!btn) return;
      if(!confirm('Delete this row?')) return;
      btn.closest('tr').remove();
      renumberBills(table);
      updateTotalsDebounced();
    };

    // Enter key navigation
    container.addEventListener('keydown',function(e){
      if(e.key==='Enter' && (e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA')){
        e.preventDefault();
        var focusables=Array.from(container.querySelectorAll('input:not([readonly]),textarea'));
        var i=focusables.indexOf(e.target);
        if(i>-1 && i<focusables.length-1) focusables[i+1].focus();
      }
    });

    updateTotalsDebounced();
  };

  /* ----------------------------------------
     Save Revision
  ---------------------------------------- */
  function saveRevision(table){
    return new Promise(function(resolve,reject){
      var form=table.closest('form')||document.querySelector('#revisionForm');
      if(!form) return reject('Form not found');
      var pid=form.querySelector('input[name="project_id"]').value;
      var rows=table.querySelectorAll('tbody tr');
      if(!rows.length) return reject('No rows');

      // Commit edits
      form.querySelectorAll('textarea[name="description[]"],input').forEach(el=>{try{el.blur();}catch(e){}});
      var fd=new FormData();
      fd.append('project_id',pid);
      rows.forEach(r=>{
        fd.append('bill_no[]',(r.querySelector('input[name="bill_no[]"]')||{value:''}).value);
        var desc=r.querySelector('textarea[name="description[]"]');
        fd.append('description[]',desc?desc.value:'');
        COLS.forEach(c=>{
          var el=r.querySelector('[data-field="'+c+'"]');
          var v=el?el.value.replace(/,/g,''):'0';
          fd.append(c+'[]',v);
        });
      });

      fetch('save_revision.php',{method:'POST',body:fd})
        .then(()=>{showToast('✅ Revision saved successfully!','#28a745',2500);setTimeout(()=>location.reload(),2200);resolve();})
        .catch(err=>{showToast('❌ Save failed: '+err,'#dc3545',3000);reject(err);});
    });
  }

  /* ----------------------------------------
     Mutation Observer: Auto-init after AJAX
  ---------------------------------------- */
  var container=document.getElementById('revisionContainer');
  if(container){
    var mo=new MutationObserver(function(muts){
      muts.forEach(function(m){
        m.addedNodes.forEach(function(node){
          if(node.nodeType===1 && node.querySelector && node.querySelector('#revisionTable')){
            setTimeout(()=>initAllRows(node.querySelector('#revisionTable')),10);
          }
        });
      });
    });
    mo.observe(container,{childList:true,subtree:true});
    var tbl=container.querySelector('#revisionTable');
    if(tbl) setTimeout(()=>initAllRows(tbl),10);
  }

})();
</script>

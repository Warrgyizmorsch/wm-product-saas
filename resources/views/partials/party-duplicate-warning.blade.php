{{--
    "Does this party already exist?" panel for the customer / vendor create forms (FRS 2.3).
    Place inside the <form>. Expects: $checkUrl (JSON lookup route).
    Shows matches found by the server on submit (session 'duplicate_matches') and re-checks live
    while the user types. When matches exist the user must tick the box to create the party anyway.
--}}
<div id="partyDuplicateWarning" class="alert alert-warning mb-4" style="display:none" role="alert">
    <div class="fw-bold mb-2"><i class="feather-alert-triangle me-1"></i>This party may already exist</div>
    <ul id="partyDuplicateList" class="mb-2 ps-3"></ul>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="confirmDuplicate" disabled>
        <label class="form-check-label" for="confirmDuplicate">This is a different party - save anyway</label>
    </div>
</div>

<script>
    (function () {
        var box = document.getElementById('partyDuplicateWarning');
        var list = document.getElementById('partyDuplicateList');
        var confirmBox = document.getElementById('confirmDuplicate');
        var form = box.closest('form');
        var fields = ['name', 'company_name', 'email', 'phone', 'gstin', 'pan', 'billing_address', 'address'];
        var timer = null;

        function render(matches) {
            list.innerHTML = '';
            matches.forEach(function (m) {
                var li = document.createElement('li');
                var link = document.createElement('a');
                link.href = m.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = m.name;
                li.appendChild(link);
                li.appendChild(document.createTextNode(
                    (m.status ? ' [' + m.status + ']' : '') + ' - same ' + m.matched.join(', ')
                ));
                list.appendChild(li);
            });
            var show = matches.length > 0;
            box.style.display = show ? '' : 'none';
            confirmBox.disabled = !show;
            if (!show) { confirmBox.checked = false; }
        }

        function check() {
            var params = new URLSearchParams();
            fields.forEach(function (name) {
                var el = form.elements[name];
                if (el && el.value.trim() !== '') { params.set(name, el.value.trim()); }
            });
            if ([...params.keys()].length === 0) { render([]); return; }

            fetch(@json($checkUrl) + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : { duplicates: [] }; })
                .then(function (data) { render(data.duplicates || []); })
                .catch(function () { /* the server re-checks on submit, so a failed lookup is harmless */ });
        }

        fields.forEach(function (name) {
            var el = form.elements[name];
            if (el) {
                el.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(check, 300); });
            }
        });

        // Matches the server found when the form was submitted (the form is re-shown with its old input).
        var initial = @json(session('duplicate_matches', []));
        if (initial.length) { render(initial); }
    })();
</script>

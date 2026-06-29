<script>
function fetchData(url, formData, action = '', method = 'POST') {
        if (action) {
            url = url + '?action=' + action;
        }
        fetch(url, {
            method: method,
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                return res.text().then(text => { throw new Error(text) });
            }
            return res.json();
        })
        .then(res => {
            return res;
        })
        .catch(error => {
            return { success: false, errors: [error.message] };
        });
}
</script>

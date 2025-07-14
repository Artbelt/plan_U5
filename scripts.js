function saveHours() {
    const inputs = document.querySelectorAll("input[name^='hours']");
    const productionDate = document.getElementById("production_date")?.value || "";
    console.log("Production date:", productionDate);
    console.log("Inputs:", Array.from(inputs).map(input => ({ name: input.name, value: input.value })));

    const formData = new FormData();
    formData.append("date", productionDate);

    inputs.forEach(input => {
        if (input.value.trim() !== '') {
            formData.append(input.name, input.value);
        }
    });

    fetch('save_hours.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                console.error('Server responded with status:', response.status);
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(result => {
            alert("✅ Часы успешно сохранены!");
            console.log("Response:", result);
        })
        .catch(error => {
            console.error("Ошибка:", error);
            alert("❌ Не удалось сохранить часы: " + error.message);
        });
}
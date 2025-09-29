import os

# Укажи путь к папке
folder_path = r"C:\xampp\htdocs\plan_U5\photo"

for filename in os.listdir(folder_path):
    file_path = os.path.join(folder_path, filename)

    # Пропускаем папки
    if os.path.isdir(file_path):
        continue

    # Проверяем, начинается ли имя файла с 'AF'
    if not filename.startswith("AF"):
        new_name = "AF" + filename
        new_path = os.path.join(folder_path, new_name)
        os.rename(file_path, new_path)
        print(f"Переименован: {filename} → {new_name}")

print("Готово!")

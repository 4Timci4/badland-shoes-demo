<?php


require_once 'config/auth.php';
check_admin_auth();


require_once '../config/database.php';
require_once '../services/GenderService.php';


$page_title = 'Cinsiyet Yönetimi';
$breadcrumb_items = [
    ['title' => 'Cinsiyet Yönetimi', 'url' => '#', 'icon' => 'fas fa-venus-mars']
];


if ($_POST) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('error', 'Güvenlik hatası. Lütfen tekrar deneyin.');
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');

                if (empty($name)) {
                    set_flash_message('error', 'Cinsiyet adı zorunludur.');
                } else {
                    $slug = gender_service()->generateSlug($name);

                    $gender_data = [
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description
                    ];

                    if (gender_service()->createGender($gender_data)) {
                        set_flash_message('success', 'Cinsiyet başarıyla eklendi.');
                    } else {
                        set_flash_message('error', 'Cinsiyet eklenirken bir hata oluştu.');
                    }
                }
                break;

            case 'edit':
                $gender_id = intval($_POST['gender_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');

                if ($gender_id > 0 && !empty($name)) {
                    $slug = gender_service()->generateSlug($name);

                    $gender_data = [
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description
                    ];

                    if (gender_service()->updateGender($gender_id, $gender_data)) {
                        set_flash_message('success', 'Cinsiyet başarıyla güncellendi.');
                    } else {
                        set_flash_message('error', 'Cinsiyet güncellenirken bir hata oluştu.');
                    }
                } else {
                    set_flash_message('error', 'Geçersiz cinsiyet bilgileri.');
                }
                break;

            case 'delete':
                $gender_id = intval($_POST['gender_id'] ?? 0);

                if ($gender_id > 0) {
                    if (gender_service()->deleteGender($gender_id)) {
                        set_flash_message('success', 'Cinsiyet başarıyla silindi.');
                    } else {
                        set_flash_message('error', 'Cinsiyet silinemedi. Bu cinsiyete ait ürünler mevcut olabilir.');
                    }
                } else {
                    set_flash_message('error', 'Geçersiz cinsiyet ID.');
                }
                break;
        }


        header('Location: genders.php');
        exit;
    }
}



function getGendersWithProductCounts()
{
    try {
        $genders = gender_service()->getAllGenders();

        foreach ($genders as &$gender) {

            $gender['product_count'] = database()->count('product_genders', ['gender_id' => $gender['id']]);
        }

        return $genders;
    } catch (Exception $e) {
        error_log("Cinsiyetler ve ürün sayıları getirme hatası: " . $e->getMessage());
        return [];
    }
}

$genders = getGendersWithProductCounts();


$edit_mode = isset($_GET['edit']) && !empty($_GET['edit']);
$edit_gender = null;

if ($edit_mode) {
    $edit_id = intval($_GET['edit']);
    $edit_gender = gender_service()->getGenderById($edit_id);
    if (empty($edit_gender)) {
        $edit_mode = false;
    }
}


include 'includes/header.php';
?>

<!-- Genders Management Content -->
<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Cinsiyet Yönetimi</h1>
            <p class="text-gray-600">Ürün cinsiyetlerini yönetin ve düzenleyin</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <a href="products.php"
                class="inline-flex items-center justify-center px-4 py-2 sm:px-6 sm:py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition-colors text-sm sm:text-base">
                <i class="fas fa-box mr-2"></i>
                Ürünlere Dön
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php
    $flash_message = get_flash_message();
    if ($flash_message):
        $bg_color = $flash_message['type'] === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
        $text_color = $flash_message['type'] === 'success' ? 'text-green-800' : 'text-red-800';
        $icon = $flash_message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        $icon_color = $flash_message['type'] === 'success' ? 'text-green-500' : 'text-red-500';
        ?>
        <div class="<?= $bg_color ?> border rounded-xl p-4 flex items-center">
            <i class="fas <?= $icon ?> <?= $icon_color ?> mr-3"></i>
            <span class="<?= $text_color ?> font-medium"><?= htmlspecialchars($flash_message['message']) ?></span>
        </div>
    <?php endif; ?>

    <!-- Quick Add / Edit Form -->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-xl font-bold text-gray-900 mb-1">
                <?= $edit_mode ? 'Cinsiyet Düzenle' : 'Yeni Cinsiyet Ekle' ?>
            </h3>
            <p class="text-gray-600 text-sm">
                <?= $edit_mode ? 'Mevcut cinsiyet bilgilerini güncelleyin' : 'Hızlıca yeni bir cinsiyet oluşturun' ?>
            </p>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="<?= $edit_mode ? 'edit' : 'add' ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="gender_id" value="<?= $edit_gender['id'] ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Gender Name -->
                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-venus-mars mr-2"></i>Cinsiyet Adı *
                        </label>
                        <input type="text" id="name" name="name" required
                            value="<?= htmlspecialchars($edit_gender['name'] ?? '') ?>"
                            placeholder="Örn: Erkek, Kadın, Çocuk"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                    </div>

                    <!-- Description -->
                    <div class="lg:col-span-2">
                        <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-2"></i>Açıklama
                        </label>
                        <input type="text" id="description" name="description"
                            value="<?= htmlspecialchars($edit_gender['description'] ?? '') ?>"
                            placeholder="Cinsiyet açıklaması (opsiyonel)"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors">
                    </div>

                    <!-- Submit Button -->
                    <div class="lg:self-end">
                        <div class="flex space-x-2">
                            <button type="submit"
                                class="flex-1 bg-primary-600 text-white font-semibold py-3 px-6 rounded-xl hover:bg-primary-700 transition-colors flex items-center justify-center">
                                <i class="fas <?= $edit_mode ? 'fa-save' : 'fa-plus' ?> mr-2"></i>
                                <?= $edit_mode ? 'Güncelle' : 'Ekle' ?>
                            </button>
                            <?php if ($edit_mode): ?>
                                <a href="genders.php"
                                    class="bg-gray-100 text-gray-700 font-semibold py-3 px-4 rounded-xl hover:bg-gray-200 transition-colors flex items-center justify-center">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Genders List -->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1">Cinsiyetler</h3>
                    <p class="text-gray-600 text-sm">Mevcut cinsiyetlerin listesi</p>
                </div>
                <div class="text-sm text-gray-500">
                    Toplam: <span class="font-semibold"><?= count($genders) ?></span> cinsiyet
                </div>
            </div>
        </div>

        <?php if (!empty($genders)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th
                                class="px-4 sm:px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Cinsiyet</th>
                            <th
                                class="hidden lg:table-cell px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Slug
                            </th>
                            <th
                                class="hidden md:table-cell px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Açıklama</th>
                            <th
                                class="hidden sm:table-cell px-6 py-4 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Ürün Sayısı</th>
                            <th
                                class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($genders as $gender): ?>
                            <tr
                                class="hover:bg-gray-50 transition-colors <?= ($edit_mode && $edit_gender['id'] == $gender['id']) ? 'bg-blue-50' : '' ?>">
                                <td class="px-4 sm:px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div
                                            class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-venus-mars text-purple-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($gender['name']) ?>
                                            </h4>
                                            <p class="text-sm text-gray-500">ID: #<?= $gender['id'] ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell px-6 py-4">
                                    <code class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm font-mono">
                                                        <?= htmlspecialchars($gender['slug']) ?>
                                                    </code>
                                </td>
                                <td class="hidden md:table-cell px-6 py-4">
                                    <span class="text-gray-600 text-sm">
                                        <?= htmlspecialchars($gender['description'] ?: 'Açıklama yok') ?>
                                    </span>
                                </td>
                                <td class="hidden sm:table-cell px-6 py-4 text-center">
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                        <?= $gender['product_count'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <i class="fas fa-box mr-1"></i>
                                        <?= $gender['product_count'] ?>
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-4 text-right">
                                    <div
                                        class="flex flex-col sm:flex-row items-center justify-end space-y-2 sm:space-y-0 sm:space-x-2">
                                        <a href="genders.php?edit=<?= $gender['id'] ?>"
                                            class="inline-flex items-center justify-center w-full sm:w-24 px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium">
                                            <i class="fas fa-edit mr-1"></i>
                                            Düzenle
                                        </a>

                                        <?php if ($gender['product_count'] == 0): ?>
                                            <form method="POST" class="inline-block w-full sm:w-auto"
                                                onsubmit="return confirm('Bu cinsiyeti silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="gender_id" value="<?= $gender['id'] ?>">
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center w-full sm:w-24 px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors text-sm font-medium">
                                                    <i class="fas fa-trash mr-1"></i>
                                                    Sil
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center justify-center w-full sm:w-24 px-3 py-2 bg-gray-100 text-gray-500 rounded-lg text-sm font-medium cursor-not-allowed"
                                                title="Bu cinsiyete ait ürünler mevcut, silinemez">
                                                <i class="fas fa-ban mr-1"></i>
                                                Silinemez
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-venus-mars text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Henüz cinsiyet yok</h3>
                <p class="text-gray-600 mb-6">İlk cinsiyeti oluşturarak başlayın</p>
                <button onclick="document.getElementById('name').focus()"
                    class="inline-flex items-center px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    İlk Cinsiyeti Ekle
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Card -->
    <?php if (!empty($genders)): ?>
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl p-6 text-white">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold mb-2"><?= count($genders) ?></div>
                    <div class="text-purple-100">Toplam Cinsiyet</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold mb-2"><?= array_sum(array_column($genders, 'product_count')) ?></div>
                    <div class="text-purple-100">Toplam Ürün</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold mb-2">
                        <?= count(array_filter($genders, function ($gender) {
                            return $gender['product_count'] > 0;
                        })) ?>
                    </div>
                    <div class="text-purple-100">Aktif Cinsiyet</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for enhanced UX -->
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const nameInput = document.getElementById('name');
        const form = nameInput.closest('form');

        nameInput.addEventListener('input', function () {

        });


        form.addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const btnText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner animate-spin mr-2"></i>İşleniyor...';


            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = btnText;
            }, 3000);
        });


        nameInput.addEventListener('blur', function () {
            if (this.value.trim().length < 2) {
                this.classList.add('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
                this.classList.remove('border-green-300', 'focus:border-green-500', 'focus:ring-green-500');
            } else {
                this.classList.remove('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
                this.classList.add('border-green-300', 'focus:border-green-500', 'focus:ring-green-500');
            }
        });
    });
</script>

<?php

include 'includes/footer.php';
?>
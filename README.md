# YO 團購 POS 系統

> 基於 Laravel 12 + Livewire + Flux UI 開發的多分店團購 POS 系統，完整繁體中文介面。

---

## 系統架構

```
VPS（Vultr Tokyo）
├── Nginx（反向代理）
├── PHP 8.5-FPM
├── MySQL 8.4
└── 各分店獨立實例（每店獨立資料庫）
```

---

## 快速操作指南

### 一、全新安裝（新 VPS）

```bash
curl -O https://raw.githubusercontent.com/surfingman1088/-POS-/main/scripts/install.sh
chmod +x install.sh
bash install.sh
```

### 二、升級系統（從 GitHub 拉取最新代碼）

```bash
bash /opt/yo-pos-source/scripts/upgrade.sh
```

或透過 **GitHub Actions** 自動部署（推送到 main 分支即自動觸發）。

### 三、新增分店

```bash
bash /opt/yo-pos-source/scripts/add-branch.sh <分店ID> <分店名稱> <埠號>

# 範例：新增中壢店（Port 8007）
bash /opt/yo-pos-source/scripts/add-branch.sh zhongli 中壢店 8007
```

### 四、備份資料庫

```bash
bash /opt/yo-pos-source/scripts/backup.sh
```

設定每日自動備份（凌晨 2 點）：

```bash
crontab -e
# 加入以下一行：
0 2 * * * bash /opt/yo-pos-source/scripts/backup.sh >> /root/backup.log 2>&1
```

### 五、查看系統狀態

```bash
bash /opt/yo-pos-source/scripts/status.sh
```

---

## GitHub Actions 自動部署設定

每次推送代碼到 `main` 分支時，GitHub Actions 會自動將最新代碼部署到 VPS。

### 設定步驟

1. 在 VPS 上生成 SSH 金鑰：
   ```bash
   ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/github_deploy -N ""
   cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys
   cat ~/.ssh/github_deploy  # 複製這段私鑰
   ```

2. 在 GitHub 儲存庫設定 Secrets：
   - 前往：`Settings` → `Secrets and variables` → `Actions`
   - 新增 `VPS_SSH_KEY`：貼上上面複製的私鑰內容
   - 新增 `VPS_HOST`：填入您的 VPS IP 位址

3. 之後每次 `git push` 到 main 分支，系統會自動更新所有分店。

---

## 功能模組

| 模組 | 功能說明 |
|------|---------|
| 儀表盤 | 即時銷售 KPI、今日營收、訂單數、庫存狀態 |
| 訂單 | 建立新訂單、選擇商品、結帳、收款 |
| 訂單記錄 | 查看歷史訂單、篩選、匯出 |
| 產品 | 商品管理（新增、編輯、刪除） |
| 類別 | 商品分類管理 |
| 庫存審計 | 庫存盤點與調整 |
| 客戶 | 客戶資料管理 |
| 員工 | 員工帳號與權限管理 |
| 折扣預設 | 設定折扣方案 |
| 帳戶與會話 | 帳號管理、登入記錄 |
| 系統日誌 | 操作記錄追蹤 |

---

## 技術棧

- **後端框架**：Laravel 12
- **前端框架**：Livewire 3 + Flux UI
- **資料庫**：MySQL 8.4
- **Web 伺服器**：Nginx 1.28
- **PHP 版本**：8.5
- **語言**：繁體中文（zh_TW）

---

## 目錄結構

```
scripts/
├── install.sh      # 全新安裝腳本（適用 Ubuntu 22.04/24.04/26.04）
├── upgrade.sh      # 升級腳本（從 GitHub 拉取最新代碼）
├── add-branch.sh   # 新增分店腳本
├── backup.sh       # 資料庫備份腳本
└── status.sh       # 系統狀態檢查腳本

lang/
├── zh.json         # 繁體中文翻譯
└── en.json         # 英文翻譯
```

---

## 常見問題

**Q：如何修改管理員密碼？**
登入後點選左下角帳號名稱 → 帳戶設定 → 修改密碼

**Q：如何新增商品？**
登入後點選左側選單「產品」→「新增產品」

**Q：系統無法存取怎麼辦？**
```bash
bash /opt/yo-pos-source/scripts/status.sh
```

**Q：如何在本機（Windows）測試？**
請參考 [本機安裝指南](docs/本機安裝指南.md)

---

*YO 團購 POS 系統 © 2026*

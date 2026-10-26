"""Проверка матрицы кампаний GO Flow перед публикацией ссылок.
Запуск: python validate_utm.py CAMPAIGN_MATRIX.csv
Ничего не отправляет во внешние сервисы, только читает CSV.
"""
import csv, re, sys

MEDIUMS = {"cpc", "display", "paid_social", "social", "email", "affiliate",
           "outreach", "job_board", "content", "referral_program", "test"}
FIELDS = ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term", "utm_adgroup", "utm_creative"]
CODE = re.compile(r"^[a-z0-9_]{1,100}$")
MACRO = re.compile(r"^(\{[a-z_]+\}|\{\{[a-z_]+\}\})$")          # макросы Директа / VK Ads
PII = [re.compile(r"@"), re.compile(r"\+?\d[\d\s\-()]{9,}"), re.compile(r"https?://|www\.", re.I)]
CAMP = re.compile(r"^(test_)?\d{4}q[1-4]_(specialists|customers)_[a-z0-9_]+$")
CHANNEL_BY_MEDIUM = {"cpc": "paid_search", "display": "display", "paid_social": "paid_social", "social": "social",
                     "email": "email", "affiliate": "partner", "outreach": "outreach", "job_board": "job_board",
                     "content": "content", "referral_program": "referral_program", "test": "exclude_test"}

def check(path):
    errors = 0
    with open(path, encoding="utf-8-sig") as f:
        for row in csv.DictReader(f):
            rid = row.get("id") or row.get("utm_campaign")
            def err(msg):
                nonlocal errors
                errors += 1
                print(f"[{rid}] {msg}")
            for k in FIELDS:
                v = (row.get(k) or "").strip()
                if not v:
                    if k in ("utm_source", "utm_medium", "utm_campaign"):
                        err(f"{k}: обязательное поле пустое")
                    continue
                if any(p.search(v) for p in PII):
                    err(f"{k}={v}: похоже на контакт или URL")
                elif not (CODE.match(v) or (k in ("utm_adgroup", "utm_creative") and MACRO.match(v))):
                    err(f"{k}={v}: только a-z 0-9 _ и до 100 символов")
            m = row.get("utm_medium", "")
            if m and m not in MEDIUMS:
                err(f"utm_medium={m}: нет в словаре")
            c = row.get("utm_campaign", "")
            if c and not CAMP.match(c):
                err(f"utm_campaign={c}: формат yyyyqN_audience_name")
            exp = row.get("expected_channel")
            if exp and m in CHANNEL_BY_MEDIUM and exp != CHANNEL_BY_MEDIUM[m]:
                err(f"expected_channel={exp} не совпадает со словарем ({CHANNEL_BY_MEDIUM[m]})")
            if "example.invalid" not in (row.get("example_url") or "") and row.get("status") == "DRAFT":
                err("DRAFT строка указывает на реальный домен")
    print(f"{path}: ошибок {errors}")
    return errors

if __name__ == "__main__":
    sys.exit(1 if sum(check(p) for p in sys.argv[1:]) else 0)

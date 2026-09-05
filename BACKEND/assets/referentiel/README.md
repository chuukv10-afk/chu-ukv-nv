# Référentiel CIM-10 FR

## Source

- **Origine officielle :** [CIM-10 FR à usage PMSI](https://www.atih.sante.fr/thematique/nomenclatures) (ATIH)
- **Fichier intermédiaire :** [nomensland / cim_hierarchie_code.json.gz](https://github.com/GuillaumePressiat/nomensland/blob/master/inst/tables/cim_hierarchie_code.json.gz)
- **Préparation CHU UKV :** `bin/prepare_cim10.py` → `cim10_import.jsonl.gz`

## Fichiers

| Fichier | Description |
|---------|-------------|
| `cim_hierarchie_code.json.gz` | Export nomensland (ATIH PMSI, historisé) |
| `cim10_import.jsonl.gz` | Jeu dédoublonné importé en base (~15 800 codes `tr` 0/3) |

## Import

```bash
# 1. Télécharger la source nomensland (si absente)
# 2. Regénérer le fichier d'import
python bin/prepare_cim10.py

# 3. Appliquer la migration puis importer
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:referentiel:import-cim10 --truncate
```

Options de la commande :

- `--truncate` : vide la table `maladie` avant import
- `--file=` : chemin vers un `.jsonl` ou `.jsonl.gz` alternatif

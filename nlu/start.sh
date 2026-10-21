#!/bin/sh
# Train on first start (about 20 s on a laptop CPU), then serve.
set -e
if [ ! -f models/intent.joblib ]; then
  python data/build_dataset.py
  python -W ignore train.py
fi
exec uvicorn snan_nlu.api:app --host 0.0.0.0 --port 8001 --reload

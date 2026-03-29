FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .

RUN pip install --no-cache-dir -r requirements.txt

# Copy all the content from the current directory
COPY . /app

EXPOSE 8000

# We use host 0.0.0.0 to make sure it's accessible externally from the container
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]

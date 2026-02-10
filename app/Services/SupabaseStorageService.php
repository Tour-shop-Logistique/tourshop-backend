<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    protected string $projectUrl;
    protected string $serviceKey;
    protected string $bucket;

    public function __construct()
    {
        $this->projectUrl = rtrim(config('supabase.url'), '/');
        $this->serviceKey = config('supabase.service_key');
        $this->bucket = config('supabase.bucket');
    }

    /**
     * Upload un fichier vers Supabase Storage
     * 
     * @param UploadedFile $file
     * @param string $path Chemin dans le bucket (ex: 'agences/logos')
     * @return string|false Le chemin du fichier uploadé ou false en cas d'erreur
     */
    public function upload(UploadedFile $file, string $path): string|false
    {
        try {
            // Générer un nom de fichier unique
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $fullPath = trim($path, '/') . '/' . $fileName;

            // URL de l'API Supabase Storage
            $url = "{$this->projectUrl}/storage/v1/object/{$this->bucket}/{$fullPath}";

            // Upload du fichier (Raw Binary pour Supabase Storage REST API)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceKey,
                'Content-Type' => $file->getMimeType(),
            ])->withBody(
                    file_get_contents($file->getRealPath()),
                    $file->getMimeType()
                )->post($url);

            if ($response->successful()) {
                return $fullPath;
            }

            Log::error('Supabase upload failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Supabase upload exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer un fichier de Supabase Storage
     * 
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        try {
            $url = "{$this->projectUrl}/storage/v1/object/{$this->bucket}/{$path}";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceKey,
            ])->delete($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Supabase delete exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir l'URL publique d'un fichier
     * 
     * @param string $path
     * @return string
     */
    public function getPublicUrl(string $path): string
    {
        return "{$this->projectUrl}/storage/v1/object/public/{$this->bucket}/{$path}";
    }

    /**
     * Vérifier si un fichier existe
     * 
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        try {
            $url = "{$this->projectUrl}/storage/v1/object/{$this->bucket}/{$path}";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceKey,
            ])->head($url);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}

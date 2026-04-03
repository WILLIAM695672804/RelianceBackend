<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    /**
     * Génère un nouveau secret 2FA et retourne le QR code en SVG
     */
    public function generateSecret(Request $request)
    {
        $user = $request->user();
        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA est déjà activée.'], 400);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        // Save temporary or final secret
        $user->forceFill([
            'two_factor_secret' => encrypt($secret)
        ])->save();

        // Générer l'URL pour l'app d'authentification
        $g2faUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // SVG QR Code
        $renderer = new ImageRenderer(
            new RendererStyle(250),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($g2faUrl);

        return response()->json([
            'secret' => $secret,
            'qr_code_svg' => $qrCodeSvg
        ]);
    }

    /**
     * Confirme et active la 2FA via le premier code
     */
    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();
        
        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA est déjà activée.'], 400);
        }

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Aucun secret généré. Veuillez d\'abord générer le secret 2FA.'], 400);
        }

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);
        
        $valid = $google2fa->verifyKey($secret, $request->code);

        if ($valid) {
            // Activer
            $user->forceFill(['two_factor_enabled' => true])->save();
            return response()->json(['message' => '2FA activée avec succès.']);
        }

        return response()->json(['message' => 'Code invalide.'], 400);
    }

    /**
     * Désactiver la 2FA
     */
    public function disable(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json(['message' => '2FA n\'est pas activée.'], 400);
        }

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        if ($google2fa->verifyKey($secret, $request->code)) {
            $user->forceFill([
                'two_factor_enabled' => false,
                'two_factor_secret' => null
            ])->save();
            
            return response()->json(['message' => '2FA a été désactivée.']);
        }

        return response()->json(['message' => 'Code invalide.'], 400);
    }
}

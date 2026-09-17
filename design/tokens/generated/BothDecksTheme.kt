// Generated from design/tokens/tokens.json. Do not edit by hand.
//
// Run `php design/tools/generate-tokens.php` to rebuild this file, and
// `php design/tools/check-tokens.php` to verify that what is committed matches the source.
//
// A colour, a size, a duration or a curve that is not in the token source does not belong in a
// product file either: add it to the source and regenerate.

package com.bothdecks.design

import android.os.VibrationEffect
import android.view.HapticFeedbackConstants
import androidx.compose.animation.core.CubicBezierEasing
import androidx.compose.animation.core.Easing
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp

/** The palette. Every value below appears exactly once in the product. */
object BdColors {
    val brand50 = Color(0xFFFDF1F8)
    val brand100 = Color(0xFFFAE4F2)
    val brand200 = Color(0xFFF1CAE3)
    val brand300 = Color(0xFFE4A9CF)
    val brand400 = Color(0xFFC77BAF)
    val brand500 = Color(0xFFAA5490)
    val brand600 = Color(0xFF883871)
    val brand700 = Color(0xFF6E2A5B)
    val brand800 = Color(0xFF541E44)
    val brand900 = Color(0xFF39142E)
    val brand950 = Color(0xFF210B1A)
    val neutral0 = Color(0xFFFFFDFB)
    val neutral50 = Color(0xFFFCF9F7)
    val neutral100 = Color(0xFFF5F1EE)
    val neutral200 = Color(0xFFE9E3DF)
    val neutral300 = Color(0xFFD6D0CB)
    val neutral400 = Color(0xFFAAA39D)
    val neutral500 = Color(0xFF807973)
    val neutral600 = Color(0xFF635C57)
    val neutral700 = Color(0xFF4D4641)
    val neutral800 = Color(0xFF322C28)
    val neutral850 = Color(0xFF28231F)
    val neutral900 = Color(0xFF1F1916)
    val neutral950 = Color(0xFF100C09)
    val interest100 = Color(0xFFD5F5DA)
    val interest200 = Color(0xFFB4E6BD)
    val interest300 = Color(0xFF89D298)
    val interest600 = Color(0xFF197037)
    val interest700 = Color(0xFF15592B)
    val interest800 = Color(0xFF0B411D)
    val interest900 = Color(0xFF062911)
    val info100 = Color(0xFFD8F0FC)
    val info300 = Color(0xFF87C8E8)
    val info600 = Color(0xFF006789)
    val info700 = Color(0xFF00526E)
    val info900 = Color(0xFF002635)
    val warning100 = Color(0xFFFFEBD2)
    val warning300 = Color(0xFFF5B75B)
    val warning600 = Color(0xFF986600)
    val warning700 = Color(0xFF704A02)
    val warning900 = Color(0xFF372100)
    val error100 = Color(0xFFFEE4E1)
    val error200 = Color(0xFFFBC6C1)
    val error300 = Color(0xFFF19E97)
    val error600 = Color(0xFFAC3031)
    val error700 = Color(0xFF892122)
    val error800 = Color(0xFF661617)
    val error900 = Color(0xFF430F0F)
    val scrimOverLight = Color(0x73100C09)
    val scrimOverDark = Color(0x99100C09)
}

/**
 * The roles, one field per role, so a screen names a role and never a palette entry.
 */
data class BdTheme(
    val surfaceCanvas: Color,
    val surfaceCard: Color,
    val surfaceSunken: Color,
    val surfaceRaised: Color,
    val surfaceInverse: Color,
    val surfaceScrim: Color,
    val textPrimary: Color,
    val textSecondary: Color,
    val textTertiary: Color,
    val textDisabled: Color,
    val textOnBrand: Color,
    val textOnInverse: Color,
    val textLink: Color,
    val borderSubtle: Color,
    val borderStrong: Color,
    val borderFocus: Color,
    val borderFocusOnFill: Color,
    val iconTertiary: Color,
    val brandFill: Color,
    val brandFillHover: Color,
    val brandFillPressed: Color,
    val brandText: Color,
    val brandTint: Color,
    val brandTintStrong: Color,
    val brandOnInverseAction: Color,
    val interestFill: Color,
    val interestFillHover: Color,
    val interestFillPressed: Color,
    val interestText: Color,
    val interestTint: Color,
    val interestOnFill: Color,
    val passFill: Color,
    val passFillHover: Color,
    val passFillPressed: Color,
    val passText: Color,
    val passTint: Color,
    val passOnFill: Color,
    val passBorder: Color,
    val matchFill: Color,
    val matchText: Color,
    val matchTint: Color,
    val matchOnFill: Color,
    val matchAccent: Color,
    val infoFill: Color,
    val infoText: Color,
    val infoTint: Color,
    val infoOnFill: Color,
    val warningFill: Color,
    val warningText: Color,
    val warningTint: Color,
    val warningOnFill: Color,
    val errorFill: Color,
    val errorFillHover: Color,
    val errorFillPressed: Color,
    val errorText: Color,
    val errorTint: Color,
    val errorOnFill: Color
)

val BdLightTheme = BdTheme(
    surfaceCanvas = BdColors.neutral50,
    surfaceCard = BdColors.neutral0,
    surfaceSunken = BdColors.neutral100,
    surfaceRaised = BdColors.neutral0,
    surfaceInverse = BdColors.neutral950,
    surfaceScrim = BdColors.scrimOverLight,
    textPrimary = BdColors.neutral950,
    textSecondary = BdColors.neutral700,
    textTertiary = BdColors.neutral600,
    textDisabled = BdColors.neutral400,
    textOnBrand = BdColors.neutral0,
    textOnInverse = BdColors.neutral50,
    textLink = BdColors.brand700,
    borderSubtle = BdColors.neutral300,
    borderStrong = BdColors.neutral500,
    borderFocus = BdColors.brand600,
    borderFocusOnFill = BdColors.neutral0,
    iconTertiary = BdColors.neutral600,
    brandFill = BdColors.brand600,
    brandFillHover = BdColors.brand700,
    brandFillPressed = BdColors.brand800,
    brandText = BdColors.brand700,
    brandTint = BdColors.brand50,
    brandTintStrong = BdColors.brand100,
    brandOnInverseAction = BdColors.brand300,
    interestFill = BdColors.interest600,
    interestFillHover = BdColors.interest700,
    interestFillPressed = BdColors.interest800,
    interestText = BdColors.interest700,
    interestTint = BdColors.interest100,
    interestOnFill = BdColors.neutral0,
    passFill = BdColors.neutral700,
    passFillHover = BdColors.neutral800,
    passFillPressed = BdColors.neutral900,
    passText = BdColors.neutral700,
    passTint = BdColors.neutral200,
    passOnFill = BdColors.neutral0,
    passBorder = BdColors.neutral700,
    matchFill = BdColors.brand700,
    matchText = BdColors.brand700,
    matchTint = BdColors.brand50,
    matchOnFill = BdColors.neutral0,
    matchAccent = BdColors.brand100,
    infoFill = BdColors.info600,
    infoText = BdColors.info700,
    infoTint = BdColors.info100,
    infoOnFill = BdColors.neutral0,
    warningFill = BdColors.warning600,
    warningText = BdColors.warning700,
    warningTint = BdColors.warning100,
    warningOnFill = BdColors.neutral0,
    errorFill = BdColors.error600,
    errorFillHover = BdColors.error700,
    errorFillPressed = BdColors.error800,
    errorText = BdColors.error700,
    errorTint = BdColors.error100,
    errorOnFill = BdColors.neutral0
)

val BdDarkTheme = BdTheme(
    surfaceCanvas = BdColors.neutral950,
    surfaceCard = BdColors.neutral900,
    surfaceSunken = BdColors.neutral850,
    surfaceRaised = BdColors.neutral800,
    surfaceInverse = BdColors.neutral50,
    surfaceScrim = BdColors.scrimOverDark,
    textPrimary = BdColors.neutral50,
    textSecondary = BdColors.neutral300,
    textTertiary = BdColors.neutral400,
    textDisabled = BdColors.neutral600,
    textOnBrand = BdColors.neutral950,
    textOnInverse = BdColors.neutral950,
    textLink = BdColors.brand300,
    borderSubtle = BdColors.neutral700,
    borderStrong = BdColors.neutral400,
    borderFocus = BdColors.brand300,
    borderFocusOnFill = BdColors.neutral950,
    iconTertiary = BdColors.neutral400,
    brandFill = BdColors.brand300,
    brandFillHover = BdColors.brand200,
    brandFillPressed = BdColors.brand100,
    brandText = BdColors.brand300,
    brandTint = BdColors.brand900,
    brandTintStrong = BdColors.brand800,
    brandOnInverseAction = BdColors.brand700,
    interestFill = BdColors.interest300,
    interestFillHover = BdColors.interest200,
    interestFillPressed = BdColors.interest100,
    interestText = BdColors.interest300,
    interestTint = BdColors.interest900,
    interestOnFill = BdColors.neutral950,
    passFill = BdColors.neutral300,
    passFillHover = BdColors.neutral200,
    passFillPressed = BdColors.neutral100,
    passText = BdColors.neutral300,
    passTint = BdColors.neutral800,
    passOnFill = BdColors.neutral950,
    passBorder = BdColors.neutral300,
    matchFill = BdColors.brand900,
    matchText = BdColors.brand300,
    matchTint = BdColors.brand800,
    matchOnFill = BdColors.neutral50,
    matchAccent = BdColors.brand300,
    infoFill = BdColors.info300,
    infoText = BdColors.info300,
    infoTint = BdColors.info900,
    infoOnFill = BdColors.neutral950,
    warningFill = BdColors.warning300,
    warningText = BdColors.warning300,
    warningTint = BdColors.warning900,
    warningOnFill = BdColors.neutral950,
    errorFill = BdColors.error300,
    errorFillHover = BdColors.error200,
    errorFillPressed = BdColors.error100,
    errorText = BdColors.error300,
    errorTint = BdColors.error900,
    errorOnFill = BdColors.neutral950
)

object BdSpace {
    val s4: Dp = 4.dp
    val s6: Dp = 6.dp
    val s8: Dp = 8.dp
    val s10: Dp = 10.dp
    val s12: Dp = 12.dp
    val s14: Dp = 14.dp
    val s16: Dp = 16.dp
    val s18: Dp = 18.dp
    val s20: Dp = 20.dp
    val s24: Dp = 24.dp
    val s32: Dp = 32.dp
    val s40: Dp = 40.dp
    val s48: Dp = 48.dp
    val s64: Dp = 64.dp
}

object BdRadius {
    val xs: Dp = 4.dp
    val sm: Dp = 8.dp
    val md: Dp = 12.dp
    val lg: Dp = 16.dp
    val xl: Dp = 24.dp
    val pill: Dp = 9999.dp
}

object BdBorderWidth {
    val hairline: Dp = 1.dp
    val input: Dp = 1.dp
    val focus: Dp = 2.dp
    val selected: Dp = 2.dp
}

object BdDash {
    val on: Dp = 4.dp
    val off: Dp = 3.dp
}

object BdIcon {
    val sizeSm: Dp = 16.dp
    val sizeMd: Dp = 20.dp
    val sizeLg: Dp = 24.dp
    val sizeXl: Dp = 32.dp
    val grid: Dp = 24.dp
    val strokeSm: Dp = 1.5.dp
    val strokeMd: Dp = 2.dp
    val strokeLg: Dp = 2.dp
    val strokeXl: Dp = 2.5.dp
    val strokeSelected: Dp = 2.5.dp
    val hitTargetIos: Dp = 44.dp
    val hitTargetAndroid: Dp = 48.dp
}

object BdControl {
    val heightSm: Dp = 32.dp
    val heightMd: Dp = 44.dp
    val heightLg: Dp = 52.dp
    val paddingXSm: Dp = 12.dp
    val paddingXMd: Dp = 16.dp
    val paddingXLg: Dp = 20.dp
    val gapSm: Dp = 6.dp
    val gapMd: Dp = 8.dp
    val gapLg: Dp = 8.dp
    val thumb: Dp = 24.dp
    val travel: Dp = 20.dp
    val box: Dp = 20.dp
}

/**
 * The stacks, most specific first. Resolving a name to a bundled resource is the application's
 * job; what belongs to the design system is which name comes first and what falls back to what.
 */
object BdFontFamily {
    val display: List<String> = listOf("Vollkorn", "Georgia", "serif")
    val text: List<String> = listOf("Commissioner", "system-ui", "sans-serif")
    val code: List<String> = listOf("Vollkorn", "Georgia", "serif")
}

/**
 * A type style, complete. `fontFeatureSettings` carries the tabular and lining figure request:
 * it is what makes a column of salaries line up, and it survived the previous handoff only as a comment.
 */
data class BdTextStyle(
    val fontSize: TextUnit,
    val lineHeight: TextUnit,
    val fontWeight: FontWeight,
    val fontFamily: List<String>,
    val letterSpacing: TextUnit,
    val fontFeatureSettings: String?
)

object BdType {
    val displayLg = BdTextStyle(
        fontSize = 40.sp,
        lineHeight = 52.sp,
        fontWeight = FontWeight(600),
        fontFamily = BdFontFamily.display,
        letterSpacing = (-0.015).em,
        fontFeatureSettings = null,
    )

    val display = BdTextStyle(
        fontSize = 32.sp,
        lineHeight = 42.sp,
        fontWeight = FontWeight(600),
        fontFamily = BdFontFamily.display,
        letterSpacing = (-0.01).em,
        fontFeatureSettings = null,
    )

    val title = BdTextStyle(
        fontSize = 24.sp,
        lineHeight = 31.9992.sp,
        fontWeight = FontWeight(600),
        fontFamily = BdFontFamily.display,
        letterSpacing = (-0.005).em,
        fontFeatureSettings = null,
    )

    val heading = BdTextStyle(
        fontSize = 18.sp,
        lineHeight = 25.0002.sp,
        fontWeight = FontWeight(600),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.em,
        fontFeatureSettings = null,
    )

    val bodyLg = BdTextStyle(
        fontSize = 18.sp,
        lineHeight = 28.0008.sp,
        fontWeight = FontWeight(400),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.em,
        fontFeatureSettings = null,
    )

    val body = BdTextStyle(
        fontSize = 16.sp,
        lineHeight = 24.sp,
        fontWeight = FontWeight(400),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.em,
        fontFeatureSettings = null,
    )

    val bodyStrong = BdTextStyle(
        fontSize = 16.sp,
        lineHeight = 24.sp,
        fontWeight = FontWeight(600),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.em,
        fontFeatureSettings = null,
    )

    val label = BdTextStyle(
        fontSize = 14.sp,
        lineHeight = 20.0004.sp,
        fontWeight = FontWeight(600),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.005.em,
        fontFeatureSettings = null,
    )

    val caption = BdTextStyle(
        fontSize = 13.sp,
        lineHeight = 17.9998.sp,
        fontWeight = FontWeight(400),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.01.em,
        fontFeatureSettings = null,
    )

    val tabular = BdTextStyle(
        fontSize = 16.sp,
        lineHeight = 24.sp,
        fontWeight = FontWeight(500),
        fontFamily = BdFontFamily.display,
        letterSpacing = 0.em,
        fontFeatureSettings = "\"tnum\" 1, \"lnum\" 1",
    )

    val code = BdTextStyle(
        fontSize = 13.sp,
        lineHeight = 17.9998.sp,
        fontWeight = FontWeight(500),
        fontFamily = BdFontFamily.code,
        letterSpacing = 0.em,
        fontFeatureSettings = "\"tnum\" 1, \"lnum\" 1",
    )

    val denseBody = BdTextStyle(
        fontSize = 13.sp,
        lineHeight = 17.9998.sp,
        fontWeight = FontWeight(400),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.em,
        fontFeatureSettings = null,
    )

    val denseLabel = BdTextStyle(
        fontSize = 12.sp,
        lineHeight = 18.sp,
        fontWeight = FontWeight(600),
        fontFamily = BdFontFamily.text,
        letterSpacing = 0.005.em,
        fontFeatureSettings = null,
    )
}

/**
 * The smallest size each surface may set, at a system font scale of one. `absolute` holds everywhere
 * and has no exception; the others are the floor for the surface they name.
 */
object BdTypeFloor {
    val mobile: TextUnit = 13.sp
    val candidateWeb: TextUnit = 13.sp
    val recruiterConsole: TextUnit = 12.sp
    val absolute: TextUnit = 12.sp
}

/**
 * The deck's drag transfer function. Four implementations draw this gesture, and a constant that lives
 * in each of them agrees with the others by luck rather than by construction.
 */
object BdGesture {
    const val commitThreshold: Float = 0.35f
    const val disarmThreshold: Float = 0.3f
    const val flickVelocity: Float = 600f
    const val rotationPerPx: Float = 0.06f
    const val rotationCap: Float = 12f
    const val verticalDamping: Float = 0.35f
    const val exitRotation: Float = 18f
    val exitDrop: Dp = 48.dp
    const val exitTravel: Float = 3f
    val nextCardRise: Dp = 10.dp
    const val nextCardScale: Float = 0.96f
}

/**
 * A shadow layer as the source states it. Compose's own elevation parameter cannot express two
 * layers with different alphas, so the values are carried and the drawing is left to the caller.
 */
data class BdShadowLayer(
    val offsetX: Dp,
    val offsetY: Dp,
    val blur: Dp,
    val spread: Dp,
    val color: Color
)

object BdElevation {
    val e0Light: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 0.dp, 0.dp, 0.dp, Color(0x00100C09)))
    val e0Dark: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 0.dp, 0.dp, 0.dp, Color(0x00100C09)))
    val e1Light: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 1.dp, 2.dp, 0.dp, Color(0x0F100C09)), BdShadowLayer(0.dp, 1.dp, 3.dp, 0.dp, Color(0x0A100C09)))
    val e1Dark: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 1.dp, 2.dp, 0.dp, Color(0x4D100C09)), BdShadowLayer(0.dp, 1.dp, 3.dp, 0.dp, Color(0x33100C09)))
    val e2Light: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 8.dp, 24.dp, -8.dp, Color(0x2E100C09)), BdShadowLayer(0.dp, 2.dp, 6.dp, 0.dp, Color(0x0F100C09)))
    val e2Dark: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 8.dp, 24.dp, -8.dp, Color(0x80100C09)), BdShadowLayer(0.dp, 2.dp, 6.dp, 0.dp, Color(0x40100C09)))
    val e3Light: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 24.dp, 48.dp, -16.dp, Color(0x47100C09)), BdShadowLayer(0.dp, 4.dp, 12.dp, 0.dp, Color(0x14100C09)))
    val e3Dark: List<BdShadowLayer> = listOf(BdShadowLayer(0.dp, 24.dp, 48.dp, -16.dp, Color(0x99100C09)), BdShadowLayer(0.dp, 4.dp, 12.dp, 0.dp, Color(0x4D100C09)))
}

object BdEasing {
    val standard: Easing = CubicBezierEasing(0.2f, 0f, 0f, 1f)
    val decelerate: Easing = CubicBezierEasing(0f, 0f, 0f, 1f)
    val accelerate: Easing = CubicBezierEasing(0.4f, 0f, 1f, 1f)
    val snap: Easing = CubicBezierEasing(0.2f, 1.2924f, 0.5f, 1f)
}

/**
 * `dampingRatio` is the ratio Compose asks for, computed from the source's mass, stiffness and
 * damping coefficient. The coefficient itself is not exposed: passing it where a ratio is expected is a
 * silent, order-of-magnitude mistake.
 */
data class BdSpring(val stiffness: Float, val dampingRatio: Float)

object BdSprings {
    val snapBack = BdSpring(stiffness = 400f, dampingRatio = 0.75f)
    val matchSettle = BdSpring(stiffness = 180f, dampingRatio = 0.8199f)
    val sheet = BdSpring(stiffness = 300f, dampingRatio = 0.9815f)
}

/**
 * How long the fade that replaces each spring lasts when the system asks for reduced motion. A spring
 * cannot be made accessible by slowing it down, because what has to go is the overshoot rather than the
 * speed, so the animation is substituted outright and this is the length of what replaces it.
 */
object BdSpringsReduced {
    const val snapBackMs: Int = 0
    const val matchSettleMs: Int = 200
    const val sheetMs: Int = 120
}

object BdMotion {
    const val instantMs: Int = 80
    const val fastMs: Int = 160
    const val baseMs: Int = 240
    const val exitRightMs: Int = 260
    const val exitLeftMs: Int = 300
    const val slowMs: Int = 360
    const val snapMs: Int = 360
    const val screenForwardMs: Int = 280
    const val sheetInMs: Int = 320
    const val matchHeadlineOffsetMs: Int = 320
    const val confirmHoldMs: Int = 800
    const val advanceGapMs: Int = 40
    const val undoWindowMs: Int = 8000
    const val celebrateMs: Int = 900
    const val reducedMs: Int = 120
    const val reducedCelebrateMs: Int = 200
}

/**
 * What each duration becomes when the system asks for reduced motion. The web has carried this
 * substitution since the first stylesheet; Android and iOS never received it in any form.
 */
object BdMotionReduced {
    const val instantMs: Int = BdMotion.instantMs
    const val fastMs: Int = BdMotion.reducedMs
    const val baseMs: Int = BdMotion.reducedMs
    const val exitRightMs: Int = BdMotion.reducedMs
    const val exitLeftMs: Int = BdMotion.reducedMs
    const val slowMs: Int = BdMotion.reducedMs
    const val snapMs: Int = BdMotion.reducedMs
    const val screenForwardMs: Int = BdMotion.reducedMs
    const val sheetInMs: Int = BdMotion.reducedMs
    const val matchHeadlineOffsetMs: Int = BdMotion.reducedMs
    const val confirmHoldMs: Int = BdMotion.confirmHoldMs
    const val advanceGapMs: Int = BdMotion.advanceGapMs
    const val undoWindowMs: Int = BdMotion.undoWindowMs
    const val celebrateMs: Int = BdMotion.reducedCelebrateMs
    const val reducedMs: Int = BdMotion.reducedMs
    const val reducedCelebrateMs: Int = BdMotion.reducedCelebrateMs
}

/**
 * A haptic event. `null` means the event is deliberately silent: a pass and a snap-back are silent by
 * design, and a silence that is written down is not the same thing as a silence nobody implemented.
 */
sealed interface BdHaptic {
    /** A view-level feedback constant, played through `View.performHapticFeedback`. */
    @JvmInline value class Feedback(val constant: Int) : BdHaptic

    /** A predefined vibration effect, played through a `Vibrator`. */
    @JvmInline value class Effect(val id: Int) : BdHaptic
}

object BdHaptics {
    val thresholdCrossed: BdHaptic? = BdHaptic.Feedback(HapticFeedbackConstants.CLOCK_TICK)
    val releaseInterest: BdHaptic? = BdHaptic.Effect(VibrationEffect.EFFECT_CLICK)
    val releasePass: BdHaptic? = null
    val snapBack: BdHaptic? = null
    val match: BdHaptic? = BdHaptic.Effect(VibrationEffect.EFFECT_HEAVY_CLICK)
    val errorBlocking: BdHaptic? = BdHaptic.Effect(VibrationEffect.EFFECT_HEAVY_CLICK)
    val refresh: BdHaptic? = BdHaptic.Effect(VibrationEffect.EFFECT_TICK)
    val undo: BdHaptic? = BdHaptic.Feedback(HapticFeedbackConstants.CLOCK_TICK)
}

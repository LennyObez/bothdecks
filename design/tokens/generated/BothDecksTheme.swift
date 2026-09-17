// Generated from design/tokens/tokens.json. Do not edit by hand.
//
// Run `php design/tools/generate-tokens.php` to rebuild this file, and
// `php design/tools/check-tokens.php` to verify that what is committed matches the source.
//
// A colour, a size, a duration or a curve that is not in the token source does not belong in a
// product file either: add it to the source and regenerate.

import SwiftUI
#if canImport(UIKit)
import UIKit
#endif

extension Color {
    /// An ARGB literal, alpha included: the scrim is the one role in the system that needs it.
    init(bdARGB argb: UInt32) {
        self.init(
            .sRGB,
            red: Double((argb >> 16) & 0xFF) / 255,
            green: Double((argb >> 8) & 0xFF) / 255,
            blue: Double(argb & 0xFF) / 255,
            opacity: Double((argb >> 24) & 0xFF) / 255
        )
    }
}

/// The palette. Every value below appears exactly once in the product.
enum BdColors {
    static let brand50 = Color(bdARGB: 0xFFFDF1F8)
    static let brand100 = Color(bdARGB: 0xFFFAE4F2)
    static let brand200 = Color(bdARGB: 0xFFF1CAE3)
    static let brand300 = Color(bdARGB: 0xFFE4A9CF)
    static let brand400 = Color(bdARGB: 0xFFC77BAF)
    static let brand500 = Color(bdARGB: 0xFFAA5490)
    static let brand600 = Color(bdARGB: 0xFF883871)
    static let brand700 = Color(bdARGB: 0xFF6E2A5B)
    static let brand800 = Color(bdARGB: 0xFF541E44)
    static let brand900 = Color(bdARGB: 0xFF39142E)
    static let brand950 = Color(bdARGB: 0xFF210B1A)
    static let neutral0 = Color(bdARGB: 0xFFFFFDFB)
    static let neutral50 = Color(bdARGB: 0xFFFCF9F7)
    static let neutral100 = Color(bdARGB: 0xFFF5F1EE)
    static let neutral200 = Color(bdARGB: 0xFFE9E3DF)
    static let neutral300 = Color(bdARGB: 0xFFD6D0CB)
    static let neutral400 = Color(bdARGB: 0xFFAAA39D)
    static let neutral500 = Color(bdARGB: 0xFF807973)
    static let neutral600 = Color(bdARGB: 0xFF635C57)
    static let neutral700 = Color(bdARGB: 0xFF4D4641)
    static let neutral800 = Color(bdARGB: 0xFF322C28)
    static let neutral850 = Color(bdARGB: 0xFF28231F)
    static let neutral900 = Color(bdARGB: 0xFF1F1916)
    static let neutral950 = Color(bdARGB: 0xFF100C09)
    static let interest100 = Color(bdARGB: 0xFFD5F5DA)
    static let interest200 = Color(bdARGB: 0xFFB4E6BD)
    static let interest300 = Color(bdARGB: 0xFF89D298)
    static let interest600 = Color(bdARGB: 0xFF197037)
    static let interest700 = Color(bdARGB: 0xFF15592B)
    static let interest800 = Color(bdARGB: 0xFF0B411D)
    static let interest900 = Color(bdARGB: 0xFF062911)
    static let info100 = Color(bdARGB: 0xFFD8F0FC)
    static let info300 = Color(bdARGB: 0xFF87C8E8)
    static let info600 = Color(bdARGB: 0xFF006789)
    static let info700 = Color(bdARGB: 0xFF00526E)
    static let info900 = Color(bdARGB: 0xFF002635)
    static let warning100 = Color(bdARGB: 0xFFFFEBD2)
    static let warning300 = Color(bdARGB: 0xFFF5B75B)
    static let warning600 = Color(bdARGB: 0xFF986600)
    static let warning700 = Color(bdARGB: 0xFF704A02)
    static let warning900 = Color(bdARGB: 0xFF372100)
    static let error100 = Color(bdARGB: 0xFFFEE4E1)
    static let error200 = Color(bdARGB: 0xFFFBC6C1)
    static let error300 = Color(bdARGB: 0xFFF19E97)
    static let error600 = Color(bdARGB: 0xFFAC3031)
    static let error700 = Color(bdARGB: 0xFF892122)
    static let error800 = Color(bdARGB: 0xFF661617)
    static let error900 = Color(bdARGB: 0xFF430F0F)
    static let scrimOverLight = Color(bdARGB: 0x73100C09)
    static let scrimOverDark = Color(bdARGB: 0x99100C09)
}

/// The roles, one property per role, so a screen names a role and never a palette entry.
struct BdTheme {
    let surfaceCanvas: Color
    let surfaceCard: Color
    let surfaceSunken: Color
    let surfaceRaised: Color
    let surfaceInverse: Color
    let surfaceScrim: Color
    let textPrimary: Color
    let textSecondary: Color
    let textTertiary: Color
    let textDisabled: Color
    let textOnBrand: Color
    let textOnInverse: Color
    let textLink: Color
    let borderSubtle: Color
    let borderStrong: Color
    let borderFocus: Color
    let borderFocusOnFill: Color
    let iconTertiary: Color
    let brandFill: Color
    let brandFillHover: Color
    let brandFillPressed: Color
    let brandText: Color
    let brandTint: Color
    let brandTintStrong: Color
    let brandOnInverseAction: Color
    let interestFill: Color
    let interestFillHover: Color
    let interestFillPressed: Color
    let interestText: Color
    let interestTint: Color
    let interestOnFill: Color
    let passFill: Color
    let passFillHover: Color
    let passFillPressed: Color
    let passText: Color
    let passTint: Color
    let passOnFill: Color
    let passBorder: Color
    let matchFill: Color
    let matchText: Color
    let matchTint: Color
    let matchOnFill: Color
    let matchAccent: Color
    let infoFill: Color
    let infoText: Color
    let infoTint: Color
    let infoOnFill: Color
    let warningFill: Color
    let warningText: Color
    let warningTint: Color
    let warningOnFill: Color
    let errorFill: Color
    let errorFillHover: Color
    let errorFillPressed: Color
    let errorText: Color
    let errorTint: Color
    let errorOnFill: Color
}

extension BdTheme {
    static let light = BdTheme(
        surfaceCanvas: BdColors.neutral50,
        surfaceCard: BdColors.neutral0,
        surfaceSunken: BdColors.neutral100,
        surfaceRaised: BdColors.neutral0,
        surfaceInverse: BdColors.neutral950,
        surfaceScrim: BdColors.scrimOverLight,
        textPrimary: BdColors.neutral950,
        textSecondary: BdColors.neutral700,
        textTertiary: BdColors.neutral600,
        textDisabled: BdColors.neutral400,
        textOnBrand: BdColors.neutral0,
        textOnInverse: BdColors.neutral50,
        textLink: BdColors.brand700,
        borderSubtle: BdColors.neutral300,
        borderStrong: BdColors.neutral500,
        borderFocus: BdColors.brand600,
        borderFocusOnFill: BdColors.neutral0,
        iconTertiary: BdColors.neutral600,
        brandFill: BdColors.brand600,
        brandFillHover: BdColors.brand700,
        brandFillPressed: BdColors.brand800,
        brandText: BdColors.brand700,
        brandTint: BdColors.brand50,
        brandTintStrong: BdColors.brand100,
        brandOnInverseAction: BdColors.brand300,
        interestFill: BdColors.interest600,
        interestFillHover: BdColors.interest700,
        interestFillPressed: BdColors.interest800,
        interestText: BdColors.interest700,
        interestTint: BdColors.interest100,
        interestOnFill: BdColors.neutral0,
        passFill: BdColors.neutral700,
        passFillHover: BdColors.neutral800,
        passFillPressed: BdColors.neutral900,
        passText: BdColors.neutral700,
        passTint: BdColors.neutral200,
        passOnFill: BdColors.neutral0,
        passBorder: BdColors.neutral700,
        matchFill: BdColors.brand700,
        matchText: BdColors.brand700,
        matchTint: BdColors.brand50,
        matchOnFill: BdColors.neutral0,
        matchAccent: BdColors.brand100,
        infoFill: BdColors.info600,
        infoText: BdColors.info700,
        infoTint: BdColors.info100,
        infoOnFill: BdColors.neutral0,
        warningFill: BdColors.warning600,
        warningText: BdColors.warning700,
        warningTint: BdColors.warning100,
        warningOnFill: BdColors.neutral0,
        errorFill: BdColors.error600,
        errorFillHover: BdColors.error700,
        errorFillPressed: BdColors.error800,
        errorText: BdColors.error700,
        errorTint: BdColors.error100,
        errorOnFill: BdColors.neutral0
    )

    static let dark = BdTheme(
        surfaceCanvas: BdColors.neutral950,
        surfaceCard: BdColors.neutral900,
        surfaceSunken: BdColors.neutral850,
        surfaceRaised: BdColors.neutral800,
        surfaceInverse: BdColors.neutral50,
        surfaceScrim: BdColors.scrimOverDark,
        textPrimary: BdColors.neutral50,
        textSecondary: BdColors.neutral300,
        textTertiary: BdColors.neutral400,
        textDisabled: BdColors.neutral600,
        textOnBrand: BdColors.neutral950,
        textOnInverse: BdColors.neutral950,
        textLink: BdColors.brand300,
        borderSubtle: BdColors.neutral700,
        borderStrong: BdColors.neutral400,
        borderFocus: BdColors.brand300,
        borderFocusOnFill: BdColors.neutral950,
        iconTertiary: BdColors.neutral400,
        brandFill: BdColors.brand300,
        brandFillHover: BdColors.brand200,
        brandFillPressed: BdColors.brand100,
        brandText: BdColors.brand300,
        brandTint: BdColors.brand900,
        brandTintStrong: BdColors.brand800,
        brandOnInverseAction: BdColors.brand700,
        interestFill: BdColors.interest300,
        interestFillHover: BdColors.interest200,
        interestFillPressed: BdColors.interest100,
        interestText: BdColors.interest300,
        interestTint: BdColors.interest900,
        interestOnFill: BdColors.neutral950,
        passFill: BdColors.neutral300,
        passFillHover: BdColors.neutral200,
        passFillPressed: BdColors.neutral100,
        passText: BdColors.neutral300,
        passTint: BdColors.neutral800,
        passOnFill: BdColors.neutral950,
        passBorder: BdColors.neutral300,
        matchFill: BdColors.brand900,
        matchText: BdColors.brand300,
        matchTint: BdColors.brand800,
        matchOnFill: BdColors.neutral50,
        matchAccent: BdColors.brand300,
        infoFill: BdColors.info300,
        infoText: BdColors.info300,
        infoTint: BdColors.info900,
        infoOnFill: BdColors.neutral950,
        warningFill: BdColors.warning300,
        warningText: BdColors.warning300,
        warningTint: BdColors.warning900,
        warningOnFill: BdColors.neutral950,
        errorFill: BdColors.error300,
        errorFillHover: BdColors.error200,
        errorFillPressed: BdColors.error100,
        errorText: BdColors.error300,
        errorTint: BdColors.error900,
        errorOnFill: BdColors.neutral950
    )
}

enum BdSpace {
    static let s4: CGFloat = 4
    static let s6: CGFloat = 6
    static let s8: CGFloat = 8
    static let s10: CGFloat = 10
    static let s12: CGFloat = 12
    static let s14: CGFloat = 14
    static let s16: CGFloat = 16
    static let s18: CGFloat = 18
    static let s20: CGFloat = 20
    static let s24: CGFloat = 24
    static let s32: CGFloat = 32
    static let s40: CGFloat = 40
    static let s48: CGFloat = 48
    static let s64: CGFloat = 64
}

enum BdRadius {
    static let xs: CGFloat = 4
    static let sm: CGFloat = 8
    static let md: CGFloat = 12
    static let lg: CGFloat = 16
    static let xl: CGFloat = 24
    static let pill: CGFloat = 9999
}

enum BdBorderWidth {
    static let hairline: CGFloat = 1
    static let input: CGFloat = 1
    static let focus: CGFloat = 2
    static let selected: CGFloat = 2
}

enum BdDash {
    static let on: CGFloat = 4
    static let off: CGFloat = 3
}

enum BdIcon {
    static let sizeSm: CGFloat = 16
    static let sizeMd: CGFloat = 20
    static let sizeLg: CGFloat = 24
    static let sizeXl: CGFloat = 32
    static let grid: CGFloat = 24
    static let strokeSm: CGFloat = 1.5
    static let strokeMd: CGFloat = 2
    static let strokeLg: CGFloat = 2
    static let strokeXl: CGFloat = 2.5
    static let strokeSelected: CGFloat = 2.5
    static let hitTargetIos: CGFloat = 44
    static let hitTargetAndroid: CGFloat = 48
}

enum BdControl {
    static let heightSm: CGFloat = 32
    static let heightMd: CGFloat = 44
    static let heightLg: CGFloat = 52
    static let paddingXSm: CGFloat = 12
    static let paddingXMd: CGFloat = 16
    static let paddingXLg: CGFloat = 20
    static let gapSm: CGFloat = 6
    static let gapMd: CGFloat = 8
    static let gapLg: CGFloat = 8
    static let thumb: CGFloat = 24
    static let travel: CGFloat = 20
    static let box: CGFloat = 20
}

/// The stacks, most specific first. On iOS a variable face is addressed by its PostScript name;
/// resolving these names to a bundled resource is the application's job.
enum BdFontFamily {
    static let display: [String] = ["Vollkorn", "Georgia", "serif"]
    static let text: [String] = ["Commissioner", "system-ui", "sans-serif"]
    static let code: [String] = ["Vollkorn", "Georgia", "serif"]
}

/// A type style, complete.
///
/// `lineSpacing` is what SwiftUI's `.lineSpacing` takes: the gap between lines, not the line box.
/// `fontFeatureSettings` carries the tabular and lining figure request that makes a column of
/// salaries line up; it is applied through a `UIFontDescriptor` feature setting.
struct BdTextStyle {
    let fontSize: CGFloat
    let lineHeight: CGFloat
    let weight: Font.Weight
    let fontFamily: [String]
    let tracking: CGFloat
    let fontFeatureSettings: String?

    var lineSpacing: CGFloat { lineHeight - fontSize }
}

enum BdType {
    static let displayLg = BdTextStyle(
        fontSize: 40,
        lineHeight: 52,
        weight: .semibold,
        fontFamily: BdFontFamily.display,
        tracking: -0.6,
        fontFeatureSettings: nil
    )

    static let display = BdTextStyle(
        fontSize: 32,
        lineHeight: 42,
        weight: .semibold,
        fontFamily: BdFontFamily.display,
        tracking: -0.32,
        fontFeatureSettings: nil
    )

    static let title = BdTextStyle(
        fontSize: 24,
        lineHeight: 31.9992,
        weight: .semibold,
        fontFamily: BdFontFamily.display,
        tracking: -0.12,
        fontFeatureSettings: nil
    )

    static let heading = BdTextStyle(
        fontSize: 18,
        lineHeight: 25.0002,
        weight: .semibold,
        fontFamily: BdFontFamily.text,
        tracking: 0,
        fontFeatureSettings: nil
    )

    static let bodyLg = BdTextStyle(
        fontSize: 18,
        lineHeight: 28.0008,
        weight: .regular,
        fontFamily: BdFontFamily.text,
        tracking: 0,
        fontFeatureSettings: nil
    )

    static let body = BdTextStyle(
        fontSize: 16,
        lineHeight: 24,
        weight: .regular,
        fontFamily: BdFontFamily.text,
        tracking: 0,
        fontFeatureSettings: nil
    )

    static let bodyStrong = BdTextStyle(
        fontSize: 16,
        lineHeight: 24,
        weight: .semibold,
        fontFamily: BdFontFamily.text,
        tracking: 0,
        fontFeatureSettings: nil
    )

    static let label = BdTextStyle(
        fontSize: 14,
        lineHeight: 20.0004,
        weight: .semibold,
        fontFamily: BdFontFamily.text,
        tracking: 0.07,
        fontFeatureSettings: nil
    )

    static let caption = BdTextStyle(
        fontSize: 13,
        lineHeight: 17.9998,
        weight: .regular,
        fontFamily: BdFontFamily.text,
        tracking: 0.13,
        fontFeatureSettings: nil
    )

    static let tabular = BdTextStyle(
        fontSize: 16,
        lineHeight: 24,
        weight: .medium,
        fontFamily: BdFontFamily.display,
        tracking: 0,
        fontFeatureSettings: "\"tnum\" 1, \"lnum\" 1"
    )

    static let code = BdTextStyle(
        fontSize: 13,
        lineHeight: 17.9998,
        weight: .medium,
        fontFamily: BdFontFamily.code,
        tracking: 0,
        fontFeatureSettings: "\"tnum\" 1, \"lnum\" 1"
    )

    static let denseBody = BdTextStyle(
        fontSize: 13,
        lineHeight: 17.9998,
        weight: .regular,
        fontFamily: BdFontFamily.text,
        tracking: 0,
        fontFeatureSettings: nil
    )

    static let denseLabel = BdTextStyle(
        fontSize: 12,
        lineHeight: 18,
        weight: .semibold,
        fontFamily: BdFontFamily.text,
        tracking: 0.06,
        fontFeatureSettings: nil
    )
}

/// The smallest size each surface may set, at a system font scale of one. `absolute` holds
/// everywhere and has no exception; the others are the floor for the surface they name.
enum BdTypeFloor {
    static let mobile: CGFloat = 13
    static let candidateWeb: CGFloat = 13
    static let recruiterConsole: CGFloat = 12
    static let absolute: CGFloat = 12
}

/// The deck's drag transfer function. Four implementations draw this gesture, and a constant that
/// lives in each of them agrees with the others by luck rather than by construction.
enum BdGesture {
    static let commitThreshold: Double = 0.35
    static let disarmThreshold: Double = 0.3
    static let flickVelocity: Double = 600
    static let rotationPerPx: Double = 0.06
    static let rotationCap: Double = 12
    static let verticalDamping: Double = 0.35
    static let exitRotation: Double = 18
    static let exitDrop: CGFloat = 48
    static let exitTravel: Double = 3
    static let nextCardRise: CGFloat = 10
    static let nextCardScale: Double = 0.96
}

/// A shadow layer as the source states it. SwiftUI's `.shadow` draws one layer at a time, so a
/// two-layer elevation is two modifiers and the values have to survive the trip.
struct BdShadowLayer {
    let offsetX: CGFloat
    let offsetY: CGFloat
    let blur: CGFloat
    let spread: CGFloat
    let color: Color
}

enum BdElevation {
    static let e0Light: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 0, blur: 0, spread: 0, color: Color(bdARGB: 0x00100C09))
    ]
    static let e0Dark: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 0, blur: 0, spread: 0, color: Color(bdARGB: 0x00100C09))
    ]
    static let e1Light: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 1, blur: 2, spread: 0, color: Color(bdARGB: 0x0F100C09)),
        BdShadowLayer(offsetX: 0, offsetY: 1, blur: 3, spread: 0, color: Color(bdARGB: 0x0A100C09))
    ]
    static let e1Dark: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 1, blur: 2, spread: 0, color: Color(bdARGB: 0x4D100C09)),
        BdShadowLayer(offsetX: 0, offsetY: 1, blur: 3, spread: 0, color: Color(bdARGB: 0x33100C09))
    ]
    static let e2Light: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 8, blur: 24, spread: -8, color: Color(bdARGB: 0x2E100C09)),
        BdShadowLayer(offsetX: 0, offsetY: 2, blur: 6, spread: 0, color: Color(bdARGB: 0x0F100C09))
    ]
    static let e2Dark: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 8, blur: 24, spread: -8, color: Color(bdARGB: 0x80100C09)),
        BdShadowLayer(offsetX: 0, offsetY: 2, blur: 6, spread: 0, color: Color(bdARGB: 0x40100C09))
    ]
    static let e3Light: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 24, blur: 48, spread: -16, color: Color(bdARGB: 0x47100C09)),
        BdShadowLayer(offsetX: 0, offsetY: 4, blur: 12, spread: 0, color: Color(bdARGB: 0x14100C09))
    ]
    static let e3Dark: [BdShadowLayer] = [
        BdShadowLayer(offsetX: 0, offsetY: 24, blur: 48, spread: -16, color: Color(bdARGB: 0x99100C09)),
        BdShadowLayer(offsetX: 0, offsetY: 4, blur: 12, spread: 0, color: Color(bdARGB: 0x4D100C09))
    ]
}

struct BdEasingCurve {
    let x1: Double
    let y1: Double
    let x2: Double
    let y2: Double

    func animation(duration: TimeInterval) -> Animation {
        .timingCurve(x1, y1, x2, y2, duration: duration)
    }
}

enum BdEasing {
    static let standard = BdEasingCurve(x1: 0.2, y1: 0, x2: 0, y2: 1)
    static let decelerate = BdEasingCurve(x1: 0, y1: 0, x2: 0, y2: 1)
    static let accelerate = BdEasingCurve(x1: 0.4, y1: 0, x2: 1, y2: 1)
    static let snap = BdEasingCurve(x1: 0.2, y1: 1.2924, x2: 0.5, y2: 1)
}

/// `response` and `dampingFraction` are what SwiftUI asks for, computed from the source's mass,
/// stiffness and damping coefficient rather than transcribed from it.
struct BdSpring {
    let response: TimeInterval
    let dampingFraction: Double

    var animation: Animation {
        .spring(response: response, dampingFraction: dampingFraction)
    }
}

enum BdSprings {
    static let snapBack = BdSpring(response: 0.3142, dampingFraction: 0.75)
    static let matchSettle = BdSpring(response: 0.4683, dampingFraction: 0.8199)
    static let sheet = BdSpring(response: 0.3628, dampingFraction: 0.9815)
}

/// How long the fade that replaces each spring lasts when the system asks for reduced motion. A spring
/// cannot be made accessible by slowing it down, because what has to go is the overshoot rather than
/// the speed, so the animation is substituted outright and this is the length of what replaces it.
enum BdSpringsReduced {
    static let snapBack: TimeInterval = 0
    static let matchSettle: TimeInterval = 0.2
    static let sheet: TimeInterval = 0.12
}

enum BdMotion {
    static let instant: TimeInterval = 0.08
    static let fast: TimeInterval = 0.16
    static let base: TimeInterval = 0.24
    static let exitRight: TimeInterval = 0.26
    static let exitLeft: TimeInterval = 0.3
    static let slow: TimeInterval = 0.36
    static let snap: TimeInterval = 0.36
    static let screenForward: TimeInterval = 0.28
    static let sheetIn: TimeInterval = 0.32
    static let matchHeadlineOffset: TimeInterval = 0.32
    static let confirmHold: TimeInterval = 0.8
    static let advanceGap: TimeInterval = 0.04
    static let undoWindow: TimeInterval = 8
    static let celebrate: TimeInterval = 0.9
    static let reduced: TimeInterval = 0.12
    static let reducedCelebrate: TimeInterval = 0.2
}

/// What each duration becomes when the system asks for reduced motion.
enum BdMotionReduced {
    static let instant: TimeInterval = BdMotion.instant
    static let fast: TimeInterval = BdMotion.reduced
    static let base: TimeInterval = BdMotion.reduced
    static let exitRight: TimeInterval = BdMotion.reduced
    static let exitLeft: TimeInterval = BdMotion.reduced
    static let slow: TimeInterval = BdMotion.reduced
    static let snap: TimeInterval = BdMotion.reduced
    static let screenForward: TimeInterval = BdMotion.reduced
    static let sheetIn: TimeInterval = BdMotion.reduced
    static let matchHeadlineOffset: TimeInterval = BdMotion.reduced
    static let confirmHold: TimeInterval = BdMotion.confirmHold
    static let advanceGap: TimeInterval = BdMotion.advanceGap
    static let undoWindow: TimeInterval = BdMotion.undoWindow
    static let celebrate: TimeInterval = BdMotion.reducedCelebrate
    static let reduced: TimeInterval = BdMotion.reduced
    static let reducedCelebrate: TimeInterval = BdMotion.reducedCelebrate
}

#if canImport(UIKit)
/// A haptic event. `nil` means the event is deliberately silent: a pass and a snap-back are silent
/// by design, and a silence that is written down is not a silence nobody implemented.
enum BdHaptic {
    case selection
    case impact(UIImpactFeedbackGenerator.FeedbackStyle)
    case notification(UINotificationFeedbackGenerator.FeedbackType)
}

enum BdHaptics {
    static let thresholdCrossed: BdHaptic? = .selection
    static let releaseInterest: BdHaptic? = .impact(.light)
    static let releasePass: BdHaptic? = nil
    static let snapBack: BdHaptic? = nil
    static let match: BdHaptic? = .impact(.heavy)
    static let errorBlocking: BdHaptic? = .impact(.rigid)
    static let refresh: BdHaptic? = .impact(.light)
    static let undo: BdHaptic? = .selection
}
#endif

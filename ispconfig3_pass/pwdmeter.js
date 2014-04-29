/*
 **    Created by: Jeff Todnem (http://www.todnem.com/)
 **    Created on: 2007-08-14
 **    Last modified: 2010-05-03
 **
 **    License Information:
 **    -------------------------------------------------------------------------
 **    Copyright (C) 2007 Jeff Todnem
 **
 **    This program is free software; you can redistribute it and/or modify it
 **    under the terms of the GNU General Public License as published by the
 **    Free Software Foundation; either version 2 of the License, or (at your
 **    option) any later version.
 **
 **    This program is distributed in the hope that it will be useful, but
 **    WITHOUT ANY WARRANTY; without even the implied warranty of
 **    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 **    General Public License for more details.
 **
 **    You should have received a copy of the GNU General Public License along
 **    with this program; if not, write to the Free Software Foundation, Inc.,
 **    59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 **
 */
String.prototype.strReverse = function () {
    var newstring = "";
    for (var s = 0; s < this.length; s++) {
        newstring = this.charAt(s) + newstring;
    }
    return newstring;
};


function chkPass(pwd, nMinPwdLen) {
    // Simultaneous variable declaration and value assignment aren't supported in IE apparently
    // so I'm forced to assign the same value individually per var to support a crappy browser *sigh*
    var nScore = 0, nLength = 0, nAlphaUC = 0, nAlphaLC = 0, nNumber = 0, nSymbol = 0, nMidChar = 0, nRequirements = 0, nAlphasOnly = 0, nNumbersOnly = 0, nUnqChar = 0, nRepChar = 0, nRepInc = 0, nConsecAlphaUC = 0, nConsecAlphaLC = 0, nConsecNumber = 0, nConsecSymbol = 0, nConsecCharType = 0, nSeqAlpha = 0, nSeqNumber = 0, nSeqSymbol = 0, nSeqChar = 0, nReqChar = 0, nMultConsecCharType = 0;
    var nMultRepChar = 1, nMultConsecSymbol = 1;
    var nMultMidChar = 2, nMultRequirements = 2, nMultConsecAlphaUC = 2, nMultConsecAlphaLC = 2, nMultConsecNumber = 2;
    var nReqCharType = 3, nMultAlphaUC = 3, nMultAlphaLC = 3, nMultSeqAlpha = 3, nMultSeqNumber = 3, nMultSeqSymbol = 3;
    var nMultLength = 4, nMultNumber = 4;
    var nMultSymbol = 6;
    var nTmpAlphaUC = "", nTmpAlphaLC = "", nTmpNumber = "", nTmpSymbol = "";
    var sAlphas = "abcdefghijklmnopqrstuvwxyz";
    var sNumerics = "01234567890";
    var sSymbols = ")!@#$%^&*()";
    nMinPwdLen = typeof nMinPwdLen !== 'undefined' ? nMinPwdLen : 8;

    if (pwd) {
        nScore = parseInt(pwd.length * nMultLength);
        nLength = pwd.length;
        var arrPwd = pwd.replace(/\s+/g, "").split(/\s*/);
        var arrPwdLen = arrPwd.length;

        for (var a = 0; a < arrPwdLen; a++) {
            if (arrPwd[a].match(/[A-Z]/g)) {
                if (nTmpAlphaUC !== "") {
                    if ((nTmpAlphaUC + 1) == a) {
                        nConsecAlphaUC++;
                        nConsecCharType++;
                    }
                }
                nTmpAlphaUC = a;
                nAlphaUC++;
            }
            else if (arrPwd[a].match(/[a-z]/g)) {
                if (nTmpAlphaLC !== "") {
                    if ((nTmpAlphaLC + 1) == a) {
                        nConsecAlphaLC++;
                        nConsecCharType++;
                    }
                }
                nTmpAlphaLC = a;
                nAlphaLC++;
            }
            else if (arrPwd[a].match(/[0-9]/g)) {
                if (a > 0 && a < (arrPwdLen - 1)) {
                    nMidChar++;
                }
                if (nTmpNumber !== "") {
                    if ((nTmpNumber + 1) == a) {
                        nConsecNumber++;
                        nConsecCharType++;
                    }
                }
                nTmpNumber = a;
                nNumber++;
            }
            else if (arrPwd[a].match(/[^a-zA-Z0-9_]/g)) {
                if (a > 0 && a < (arrPwdLen - 1)) {
                    nMidChar++;
                }
                if (nTmpSymbol !== "") {
                    if ((nTmpSymbol + 1) == a) {
                        nConsecSymbol++;
                        nConsecCharType++;
                    }
                }
                nTmpSymbol = a;
                nSymbol++;
            }

            var bCharExists = false;
            for (var b = 0; b < arrPwdLen; b++) {
                if (arrPwd[a] == arrPwd[b] && a != b) {
                    bCharExists = true;
                    nRepInc += Math.abs(arrPwdLen / (b - a));
                }
            }
            if (bCharExists) {
                nRepChar++;
                nUnqChar = arrPwdLen - nRepChar;
                nRepInc = (nUnqChar) ? Math.ceil(nRepInc / nUnqChar) : Math.ceil(nRepInc);
            }
        }

        /* Check for sequential alpha string patterns (forward and reverse) */
        for (var s = 0; s < 23; s++) {
            var sFwd = sAlphas.substring(s, parseInt(s + 3));
            var sRev = sFwd.strReverse();
            if (pwd.toLowerCase().indexOf(sFwd) != -1 || pwd.toLowerCase().indexOf(sRev) != -1) {
                nSeqAlpha++;
                nSeqChar++;
            }
        }

        /* Check for sequential numeric string patterns (forward and reverse) */
        for (var s = 0; s < 8; s++) {
            var sFwd = sNumerics.substring(s, parseInt(s + 3));
            var sRev = sFwd.strReverse();
            if (pwd.toLowerCase().indexOf(sFwd) != -1 || pwd.toLowerCase().indexOf(sRev) != -1) {
                nSeqNumber++;
                nSeqChar++;
            }
        }

        /* Check for sequential symbol string patterns (forward and reverse) */
        for (var s = 0; s < 8; s++) {
            var sFwd = sSymbols.substring(s, parseInt(s + 3));
            var sRev = sFwd.strReverse();
            if (pwd.toLowerCase().indexOf(sFwd) != -1 || pwd.toLowerCase().indexOf(sRev) != -1) {
                nSeqSymbol++;
                nSeqChar++;
            }
        }

        /* Modify overall score value based on usage vs requirements */

        /* General point assignment */
        if (nAlphaUC > 0 && nAlphaUC < nLength) {
            nScore = parseInt(nScore + ((nLength - nAlphaUC) * 2));
        }
        if (nAlphaLC > 0 && nAlphaLC < nLength) {
            nScore = parseInt(nScore + ((nLength - nAlphaLC) * 2));
        }
        if (nNumber > 0 && nNumber < nLength) {
            nScore = parseInt(nScore + (nNumber * nMultNumber));
        }
        if (nSymbol > 0) {
            nScore = parseInt(nScore + (nSymbol * nMultSymbol));
        }
        if (nMidChar > 0) {
            nScore = parseInt(nScore + (nMidChar * nMultMidChar));
        }

        /* Point deductions for poor practices */
        if ((nAlphaLC > 0 || nAlphaUC > 0) && nSymbol === 0 && nNumber === 0) {  // Only Letters
            nScore = parseInt(nScore - nLength);
            nAlphasOnly = nLength;
        }
        if (nAlphaLC === 0 && nAlphaUC === 0 && nSymbol === 0 && nNumber > 0) {  // Only Numbers
            nScore = parseInt(nScore - nLength);
            nNumbersOnly = nLength;
        }
        if (nRepChar > 0) {  // Same character exists more than once
            nScore = parseInt(nScore - nRepInc);
        }
        if (nConsecAlphaUC > 0) {  // Consecutive Uppercase Letters exist
            nScore = parseInt(nScore - (nConsecAlphaUC * nMultConsecAlphaUC));
        }
        if (nConsecAlphaLC > 0) {  // Consecutive Lowercase Letters exist
            nScore = parseInt(nScore - (nConsecAlphaLC * nMultConsecAlphaLC));
        }
        if (nConsecNumber > 0) {  // Consecutive Numbers exist
            nScore = parseInt(nScore - (nConsecNumber * nMultConsecNumber));
        }
        if (nSeqAlpha > 0) {  // Sequential alpha strings exist (3 characters or more)
            nScore = parseInt(nScore - (nSeqAlpha * nMultSeqAlpha));
        }
        if (nSeqNumber > 0) {  // Sequential numeric strings exist (3 characters or more)
            nScore = parseInt(nScore - (nSeqNumber * nMultSeqNumber));
        }
        if (nSeqSymbol > 0) {  // Sequential symbol strings exist (3 characters or more)
            nScore = parseInt(nScore - (nSeqSymbol * nMultSeqSymbol));
        }

        /* Determine if mandatory requirements have been met and set image indicators accordingly */
        var arrChars = [nLength, nAlphaUC, nAlphaLC, nNumber, nSymbol];
        var arrCharsLen = arrChars.length;
        for (var c = 0; c < arrCharsLen; c++) {
            if (c == 0) {
                var minVal = parseInt(nMinPwdLen - 1);
            } else {
                var minVal = 0;
            }
            if (arrChars[c] >= parseInt(minVal + 1)) {
                nReqChar++;
            }
        }

        nRequirements = nReqChar;
        if (pwd.length >= nMinPwdLen) {
            var nMinReqChars = 3;
        } else {
            var nMinReqChars = 4;
        }
        if (nRequirements > nMinReqChars) {  // One or more required characters exist
            nScore = parseInt(nScore + (nRequirements * 2));
        }

        /* Determine complexity based on overall score */
        if (nScore > 100) {
            nScore = 100;
        } else if (nScore < 0) {
            nScore = 0;
        }
    }
    else {
        nScore;
    }

    return nScore;
}